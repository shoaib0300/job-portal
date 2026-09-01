<?php

declare(strict_types=1);

namespace KaamFit\Jobs\Sources;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use KaamFit\Jobs\JobCache;
use KaamFit\Jobs\JobHttp;
use KaamFit\Jobs\JobListing;
use KaamFit\Jobs\JobQuery;
use KaamFit\Jobs\JobStore;
use KaamFit\Jobs\JobText;

/**
 * CITTI-style retail job portals (e.g. meine-karriere-im-handel.de).
 */
final class MeineKarrierePortalSource
{
    private const MAX_PAGES = 40;
    private const PAGE_BATCH = 10;

    /**
     * @param list<array{type:string,slug:string,label:string,url:string}> $boards
     * @return array{listings: list<JobListing>, ok: int, notice: ?string}
     */
    public static function searchBoards(array $boards, JobQuery $query): array
    {
        $listings = [];
        $ok = 0;
        $notices = [];

        foreach ($boards as $board) {
            $result = self::search($query, $board);
            $listings = array_merge($listings, $result['listings']);
            if ($result['listings'] !== []) {
                $ok++;
            }
            if ($result['notice']) {
                $notices[] = $result['notice'];
            }
        }

        return [
            'listings' => $listings,
            'ok' => $ok,
            'notice' => $notices !== [] ? implode(' ', $notices) : null,
        ];
    }

    /**
     * @param array{type:string,slug:string,label:string,url:string} $board
     * @return array{listings: list<JobListing>, notice: ?string}
     */
    public static function search(JobQuery $query, array $board): array
    {
        $origin = self::originFromBoard($board);
        if ($origin === '') {
            return ['listings' => [], 'notice' => 'Portal board missing host.'];
        }

        $label = trim((string) ($board['label'] ?? 'Employer'));
        $host = (string) ($board['slug'] ?? parse_url($origin, PHP_URL_HOST) ?? '');
        $maxPage = self::detectMaxPage($origin);
        $keywords = $query->keywords;
        $cityNeedle = mb_strtolower(trim($query->city));

        $listings = [];
        for ($batchStart = 0; $batchStart <= $maxPage; $batchStart += self::PAGE_BATCH) {
            $requests = [];
            for ($page = $batchStart; $page < $batchStart + self::PAGE_BATCH && $page <= $maxPage; $page++) {
                $requests['p' . $page] = [
                    'url' => self::listUrl($origin, $page),
                    'headers' => self::htmlHeaders(),
                ];
            }
            $bodies = JobHttp::multiGet($requests, 12);
            foreach ($bodies as $html) {
                if (!is_string($html) || $html === '') {
                    continue;
                }
                foreach (self::parseList($html, $origin, $host, $label) as $job) {
                    if ($keywords !== [] && !JobText::matchesAnyKeyword($job->title . ' ' . $job->city . ' ' . $job->company, $keywords)) {
                        continue;
                    }
                    if ($cityNeedle !== '' && $job->city !== '' && !str_contains(mb_strtolower($job->city), $cityNeedle)) {
                        continue;
                    }
                    $listings[] = $job;
                }
            }
        }

        $listings = self::dedupeListings($listings);
        if ($listings === []) {
            return [
                'listings' => [],
                'notice' => ($board['label'] ?? $host) . ': portal did not return listings.',
            ];
        }

        return ['listings' => $listings, 'notice' => null];
    }

    public static function details(string $externalId): ?JobListing
    {
        if (!str_starts_with($externalId, 'portal:')) {
            return null;
        }

        $parsed = self::parseExternalId($externalId);
        if ($parsed === null) {
            return null;
        }

        $cached = JobCache::getListing('career', $externalId);
        if ($cached === null) {
            $cached = JobStore::get('career', $externalId);
        }
        if ($cached !== null && mb_strlen(trim(strip_tags($cached->description))) >= 200) {
            return $cached;
        }

        $url = $cached !== null && trim($cached->url) !== ''
            ? trim($cached->url)
            : $parsed['origin'] . '/stellenangebot/' . rawurlencode($parsed['id']);

        $html = JobHttp::get($url, self::htmlHeaders(), 18);
        if (!is_string($html) || $html === '') {
            return $cached;
        }

        $fresh = self::parseDetail($html, $externalId, $parsed['origin'], $parsed['host'], $cached);
        if ($fresh !== null) {
            JobCache::putListing($fresh);
            JobStore::upsertMany([$fresh]);
        }

        return $fresh ?? $cached;
    }

    /**
     * @param array{type:string,slug:string,label:string,url:string} $board
     */
    private static function originFromBoard(array $board): string
    {
        $url = trim((string) ($board['url'] ?? ''));
        if ($url !== '' && preg_match('#^(https?://[^/]+)#i', $url, $m)) {
            return $m[1];
        }
        $host = trim((string) ($board['slug'] ?? ''));
        if ($host === '') {
            return '';
        }

        return 'https://' . ltrim($host, '/');
    }

    private static function listUrl(string $origin, int $page): string
    {
        $params = [
            'search' => [
                'page' => $page,
                'sort' => 'titleAsc',
            ],
        ];

        return $origin . '/jobsuche?' . http_build_query($params);
    }

    private static function detectMaxPage(string $origin): int
    {
        $html = JobHttp::get(self::listUrl($origin, 0), self::htmlHeaders(), 14);
        if (!is_string($html) || $html === '') {
            return self::MAX_PAGES;
        }
        if (!preg_match_all('/search%5Bpage%5D=(\d+)/', $html, $m)) {
            return 0;
        }
        $max = 0;
        foreach ($m[1] as $page) {
            $max = max($max, (int) $page);
        }

        return min(self::MAX_PAGES, $max);
    }

    /** @return array{host:string,id:string,origin:string}|null */
    private static function parseExternalId(string $externalId): ?array
    {
        if (!preg_match('~^portal:([^:]+):(\d+)$~', $externalId, $m)) {
            return null;
        }

        $host = $m[1];
        return [
            'host' => $host,
            'id' => $m[2],
            'origin' => 'https://' . $host,
        ];
    }

    /**
     * @return list<JobListing>
     */
    private static function parseList(string $html, string $origin, string $host, string $label): array
    {
        $dom = self::dom($html);
        if ($dom === null) {
            return [];
        }

        $xp = new DOMXPath($dom);
        $out = [];
        foreach ($xp->query('//a[contains(@class,"job-opening__item") or (contains(@class,"d-block") and contains(@href,"/stellenangebot/"))]') as $a) {
            if (!$a instanceof DOMElement) {
                continue;
            }
            $href = trim($a->getAttribute('href'));
            if ($href === '' || !preg_match('~/stellenangebot/(\d+)~', $href, $m)) {
                continue;
            }
            $id = $m[1];
            $title = self::firstText($xp, $a, './/h1|.//h2|.//h3|.//h4');
            if ($title === '') {
                continue;
            }

            $lines = [];
            foreach ($xp->query('.//*[not(self::svg) and not(ancestor::svg)]', $a) as $node) {
                if (!$node instanceof DOMElement) {
                    continue;
                }
                $text = trim(preg_replace('/\s+/u', ' ', $node->textContent ?? '') ?? '');
                if ($text !== '' && mb_strlen($text) < 120) {
                    $lines[] = $text;
                }
            }
            $lines = array_values(array_unique($lines));
            $city = '';
            foreach ($lines as $line) {
                if ($line === $title) {
                    continue;
                }
                if (preg_match('/\b(mit Berufserfahrung|ohne Berufserfahrung|Teilzeit|Vollzeit|Werkstudent|Ausbildung|Praktikum)\b/ui', $line)) {
                    continue;
                }
                if (preg_match('/^[A-ZÄÖÜ][\p{L}\-]+(?:\s+[A-ZÄÖÜ][\p{L}\-]+)*$/u', $line)) {
                    $city = $line;
                    break;
                }
            }

            $hint = implode(' ', $lines);
            [$employment, $offerType, $tags] = self::employmentFromHint($hint . ' ' . $title);
            $url = str_starts_with($href, 'http') ? $href : $origin . $href;
            $externalId = 'portal:' . $host . ':' . $id;

            $job = new JobListing(
                'career',
                $externalId,
                $title,
                $label,
                $city,
                '',
                'Germany',
                'unknown',
                $employment,
                $offerType,
                $tags,
                [],
                '',
                null,
                $url,
                '',
                '',
                $url,
            );
            $out[] = JobText::enrich($job);
        }

        // Fallback regex when DOM classes differ.
        if ($out === [] && preg_match_all(
            '~<a[^>]+href="(/stellenangebot/(\d+)[^"]*)"[^>]*class="[^"]*d-block[^"]*"[^>]*>(.*?)</a>~is',
            $html,
            $matches,
            PREG_SET_ORDER
        )) {
            foreach ($matches as $row) {
                $id = $row[2];
                $body = $row[3];
                $title = '';
                if (preg_match('/<h[1-4][^>]*>(.*?)<\/h[1-4]>/is', $body, $tm)) {
                    $title = trim(strip_tags($tm[1]));
                }
                if ($title === '') {
                    continue;
                }
                $externalId = 'portal:' . $host . ':' . $id;
                $url = $origin . html_entity_decode($row[1], ENT_QUOTES | ENT_HTML5);
                $job = new JobListing(
                    'career',
                    $externalId,
                    $title,
                    $label,
                    '',
                    '',
                    'Germany',
                    'unknown',
                    'unknown',
                    'job',
                    ['student'],
                    [],
                    '',
                    null,
                    $url,
                    '',
                    '',
                    $url,
                );
                $out[] = JobText::enrich($job);
            }
        }

        return $out;
    }

    private static function parseDetail(
        string $html,
        string $externalId,
        string $origin,
        string $host,
        ?JobListing $cached
    ): ?JobListing {
        $dom = self::dom($html);
        if ($dom === null) {
            return $cached;
        }

        $xp = new DOMXPath($dom);
        $title = self::firstText($xp, $dom, '//h1');
        if ($title === '' && $cached !== null) {
            $title = $cached->title;
        }
        if ($title === '') {
            return $cached;
        }

        $company = $cached->company ?? 'Employer';
        foreach ($xp->query('//a[contains(@aria-label,"Mehr über")]') as $a) {
            if (!$a instanceof DOMElement) {
                continue;
            }
            $aria = html_entity_decode($a->getAttribute('aria-label'), ENT_QUOTES | ENT_HTML5);
            if (preg_match('/Mehr über "(.+)" lesen/u', $aria, $m)) {
                $company = trim($m[1]);
                break;
            }
        }

        $sections = [];
        foreach ($xp->query('//div[contains(@class,"panel")]') as $panel) {
            if (!$panel instanceof DOMElement) {
                continue;
            }
            $heading = self::firstText($xp, $panel, './/h2|.//h3');
            $body = trim(preg_replace('/\s+/u', ' ', $panel->textContent ?? '') ?? '');
            if ($heading === '' || $body === '' || str_contains(mb_strtolower($body), 'vimeo')) {
                continue;
            }
            $sections[] = '<h3>' . htmlspecialchars($heading, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</h3>'
                . '<p>' . htmlspecialchars($body, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>';
        }

        $intro = self::firstText($xp, $dom, '//main//p');
        $desc = implode("\n", $sections);
        if ($intro !== '' && !str_contains($desc, $intro)) {
            $desc = '<p>' . htmlspecialchars($intro, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>' . $desc;
        }

        $city = $cached->city ?? '';
        if ($city === '') {
            if (preg_match('/\b(?:Standort|in)\s+([A-ZÄÖÜ][\p{L}\-]+(?:\s+[A-ZÄÖÜ][\p{L}\-]+)*)/u', $desc, $m)) {
                $city = trim($m[1]);
            }
        }

        $url = $cached !== null && $cached->url !== ''
            ? $cached->url
            : $origin . '/stellenangebot/' . rawurlencode((string) (self::parseExternalId($externalId)['id'] ?? ''));

        $tags = $cached->seniorityTags ?? [];
        [$employment, $offerType, $empTags] = self::employmentFromHint($title . ' ' . JobText::stripHtml($desc));
        $tags = array_values(array_unique(array_merge($tags, $empTags)));

        $job = new JobListing(
            'career',
            $externalId,
            $title,
            $company !== '' ? $company : 'Employer',
            $city,
            $cached->bundesland ?? '',
            'Germany',
            $cached->workMode ?? 'unknown',
            $employment,
            $offerType,
            $tags !== [] ? $tags : ['student'],
            $cached->languages ?? [],
            $cached->salaryText ?? '',
            $cached->postedAt ?? null,
            $url,
            $desc,
            '',
            $url,
        );

        return JobText::enrich($job);
    }

    /**
     * @return array{0:string,1:string,2:list<string>}
     */
    private static function employmentFromHint(string $hint): array
    {
        $low = mb_strtolower($hint);
        $tags = [];
        $employment = 'unknown';
        $offerType = 'job';

        if (str_contains($low, 'werkstudent')) {
            $tags[] = 'student';
            $employment = 'parttime';
            $offerType = 'internship';
        }
        if (str_contains($low, 'praktikum') || str_contains($low, 'internship')) {
            $tags[] = 'internship';
            $offerType = 'internship';
        }
        if (str_contains($low, 'ausbildung') || str_contains($low, 'azubi')) {
            $tags[] = 'graduate';
            $offerType = 'internship';
        }
        if (str_contains($low, 'minijob')) {
            $tags[] = 'minijob';
            $employment = 'parttime';
        }
        if (str_contains($low, 'teilzeit')) {
            $employment = 'parttime';
        }
        if (str_contains($low, 'vollzeit')) {
            $employment = 'fulltime';
        }

        return [$employment, $offerType, array_values(array_unique($tags))];
    }

    /** @param list<JobListing> $listings @return list<JobListing> */
    private static function dedupeListings(array $listings): array
    {
        $seen = [];
        $out = [];
        foreach ($listings as $job) {
            if (isset($seen[$job->externalId])) {
                continue;
            }
            $seen[$job->externalId] = true;
            $out[] = $job;
        }

        return $out;
    }

    /** @return list<string> */
    private static function htmlHeaders(): array
    {
        return [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: de-DE,de;q=0.9,en;q=0.5',
            'User-Agent: Mozilla/5.0 (compatible; MNK-Jobs/1.1; +https://mnk.ddev.site/)',
        ];
    }

    private static function firstText(DOMXPath $xp, DOMNode $ctx, string $query): string
    {
        $n = $xp->query($query, $ctx);
        if ($n === false || $n->length === 0) {
            return '';
        }

        return trim(preg_replace('/\s+/u', ' ', $n->item(0)?->textContent ?? '') ?? '');
    }

    private static function dom(string $html): ?DOMDocument
    {
        $dom = new DOMDocument();
        $ok = @$dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);

        return $ok ? $dom : null;
    }
}
