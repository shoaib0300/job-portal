<?php

declare(strict_types=1);

namespace KaamMilo\Jobs\Sources;

use App;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use KaamMilo\Jobs\JobCache;
use KaamMilo\Jobs\JobHttp;
use KaamMilo\Jobs\JobListing;
use KaamMilo\Jobs\JobQuery;
use KaamMilo\Jobs\JobText;


/**
 * LinkedIn jobs via LinkedIn’s public guest job-search endpoints
 * (no Bright Data Marketplace / web_data / datasets API).
 *
 * Germany-only + max 7-day window. Empty/blocked responses stay empty
 * (dev notice only) — never falls back to Bright Data LinkedIn products.
 */
final class LinkedInSource
{
    private const SEARCH_URL = 'https://www.linkedin.com/jobs-guest/jobs/api/seeMoreJobPostings/search';
    private const DETAIL_URL = 'https://www.linkedin.com/jobs-guest/jobs/api/jobPosting/';
    /** LinkedIn geoId for Germany */
    private const GEO_GERMANY = '102282651';
    private const PAGE_SIZE = 25;

    /**
     * @return array{listings: list<JobListing>, notices: list<string>}
     */
    public static function search(JobQuery $query): array
    {
        $req = self::httpSearchRequest($query);
        $html = JobHttp::get($req['url'], $req['headers'], 10);
        return self::listingsFromHtml($html, $query);
    }

    /**
     * @return array{url:string,headers:list<string>}
     */
    public static function httpSearchRequest(JobQuery $query): array
    {
        $keywords = trim($query->searchWas());
        if ($keywords === '') {
            $keywords = 'Software';
        }
        $location = $query->city !== ''
            ? $query->city . ', Germany'
            : ($query->bundesland !== '' ? $query->bundesland . ', Germany' : 'Germany');
        $params = [
            'keywords' => $keywords,
            'location' => $location,
            'geoId' => self::GEO_GERMANY,
            'f_TPR' => $query->effectivePostedDays() === 1 ? 'r86400' : 'r604800',
            'start' => '0',
            'position' => '1',
            'pageNum' => '0',
        ];
        $wt = match ($query->workMode) {
            'remote' => '2',
            'onsite' => '1',
            'hybrid' => '3',
            default => '',
        };
        if ($wt !== '') {
            $params['f_WT'] = $wt;
        }
        $jt = match ($query->employment) {
            'fulltime' => 'F',
            'parttime' => 'P',
            default => '',
        };
        if ($query->internship) {
            $jt = 'I';
        }
        if ($jt !== '') {
            $params['f_JT'] = $jt;
        }

        return [
            'url' => self::SEARCH_URL . '?' . http_build_query($params),
            'headers' => self::headers(),
        ];
    }

    /**
     * @return array{listings: list<JobListing>, notices: list<string>}
     */
    public static function listingsFromHtml(?string $html, JobQuery $query): array
    {
        if ($html === null || trim($html) === '') {
            $notice = App::isDev()
                ? 'LinkedIn guest search returned empty or was blocked — no Bright Data fallback.'
                : null;
            return [
                'listings' => [],
                'notices' => $notice !== null ? [$notice] : [],
            ];
        }
        $cards = self::parseCards($html);
        if ($cards === []) {
            $notice = App::isDev()
                ? 'LinkedIn guest search had no parseable job cards.'
                : null;
            return [
                'listings' => [],
                'notices' => $notice !== null ? [$notice] : [],
            ];
        }
        $maxAge = JobQuery::MAX_POSTED_DAYS * 86400;
        $pending = [];
        foreach ($cards as $card) {
            $job = self::toListing($card, $query);
            if ($job === null) {
                continue;
            }
            if ($job->postedAt !== null) {
                $ts = strtotime($job->postedAt);
                if ($ts !== false && $ts < (time() - $maxAge)) {
                    continue;
                }
            }
            $pending[] = $job;
        }
        $detailReqs = [];
        $n = 0;
        foreach ($pending as $job) {
            if ($n >= 3) {
                break;
            }
            if ($job->description === '' && ctype_digit($job->externalId)) {
                $detailReqs[$job->externalId] = [
                    'url' => self::DETAIL_URL . $job->externalId,
                    'headers' => self::headers(),
                ];
                $n++;
            }
        }
        $detailBodies = $detailReqs !== [] ? JobHttp::multiGet($detailReqs, 8) : [];
        $listings = [];
        foreach ($pending as $job) {
            $raw = $detailBodies[$job->externalId] ?? null;
            if (is_string($raw) && $raw !== '' && $job->description === '') {
                $desc = self::parseDetailSnippet($raw);
                if ($desc !== '') {
                    $job->description = $desc;
                }
            }
            $listings[] = JobText::enrich($job);
        }
        return ['listings' => $listings, 'notices' => []];
    }

    /** @return list<string> */
    private static function headers(): array
    {
        return [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: de-DE,de;q=0.9,en-US;q=0.8,en;q=0.7',
            'User-Agent: Mozilla/5.0 (compatible; MNK-Jobs/1.0; +https://kaammilo.local)',
        ];
    }

    /**
     * @return list<array{
     *   id: string,
     *   title: string,
     *   company: string,
     *   location: string,
     *   url: string,
     *   posted: ?string,
     *   snippet: string
     * }>
     */
    private static function parseCards(string $html): array
    {
        $cards = [];
        $prev = libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $wrapped = '<?xml encoding="UTF-8"><div>' . $html . '</div>';
        if (!$dom->loadHTML($wrapped, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET)) {
            libxml_clear_errors();
            libxml_use_internal_errors($prev);
            return self::parseCardsRegex($html);
        }
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        $xpath = new DOMXPath($dom);
        $nodes = $xpath->query(
            '//*[contains(concat(" ", normalize-space(@class), " "), " base-card ")'
            . ' or contains(concat(" ", normalize-space(@class), " "), " job-search-card ")'
            . ' or @data-entity-urn]'
        );
        if ($nodes === false || $nodes->length === 0) {
            return self::parseCardsRegex($html);
        }

        $seen = [];
        foreach ($nodes as $node) {
            if (!$node instanceof DOMElement) {
                continue;
            }
            $urn = trim($node->getAttribute('data-entity-urn'));
            $id = '';
            if (preg_match('/jobPosting:(\d+)/', $urn, $m)) {
                $id = $m[1];
            }

            $title = self::xpathText($xpath, $node, './/*[contains(@class,"base-search-card__title")]');
            $company = self::xpathText($xpath, $node, './/*[contains(@class,"base-search-card__subtitle")]');
            $location = self::xpathText($xpath, $node, './/*[contains(@class,"job-search-card__location")]');
            $snippet = self::xpathText($xpath, $node, './/*[contains(@class,"job-search-card__snippet")]');

            $url = '';
            $link = $xpath->query('.//a[contains(@class,"base-card__full-link") or contains(@href,"/jobs/view/")]', $node);
            if ($link !== false && $link->length > 0 && $link->item(0) instanceof DOMElement) {
                $url = trim($link->item(0)->getAttribute('href'));
            }
            if ($url === '') {
                $any = $xpath->query('.//a[@href]', $node);
                if ($any !== false && $any->length > 0 && $any->item(0) instanceof DOMElement) {
                    $url = trim($any->item(0)->getAttribute('href'));
                }
            }
            $url = self::normalizeJobUrl($url);
            if ($id === '' && preg_match('#/jobs/view/(?:[\w-]+-)?(\d+)#', $url, $m)) {
                $id = $m[1];
            }

            $posted = null;
            $timeNodes = $xpath->query('.//time[@datetime]', $node);
            if ($timeNodes !== false && $timeNodes->length > 0 && $timeNodes->item(0) instanceof DOMElement) {
                $dt = trim($timeNodes->item(0)->getAttribute('datetime'));
                if ($dt !== '') {
                    $ts = strtotime($dt);
                    $posted = $ts !== false ? date('Y-m-d', $ts) : JobText::parsePostedDate($dt);
                }
                if ($posted === null) {
                    $posted = JobText::parsePostedDate(trim($timeNodes->item(0)->textContent ?? ''));
                }
            }

            if ($title === '' || $url === '') {
                continue;
            }
            $key = $id !== '' ? $id : $url;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            if (count($cards) >= self::PAGE_SIZE) {
                break;
            }
            $cards[] = [
                'id' => $id !== '' ? $id : hash('sha256', $url),
                'title' => $title,
                'company' => $company,
                'location' => $location,
                'url' => $url,
                'posted' => $posted,
                'snippet' => $snippet,
            ];
        }

        return $cards !== [] ? $cards : self::parseCardsRegex($html);
    }

    /**
     * Fallback when DOM structure drifts.
     *
     * @return list<array{id:string,title:string,company:string,location:string,url:string,posted:?string,snippet:string}>
     */
    private static function parseCardsRegex(string $html): array
    {
        $cards = [];
        if (!preg_match_all(
            '#href="(https?://(?:www\.)?linkedin\.com/jobs/view/[^"]+)"[^>]*>.*?'
            . 'base-search-card__title[^>]*>(.*?)</(?:h3|a|div|span)>.*?'
            . 'base-search-card__subtitle[^>]*>(.*?)</(?:h4|a|div|span)>#is',
            $html,
            $matches,
            PREG_SET_ORDER
        )) {
            return [];
        }

        foreach ($matches as $m) {
            $url = self::normalizeJobUrl(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $title = trim(JobText::stripHtml($m[2]));
            $company = trim(JobText::stripHtml($m[3]));
            $id = '';
            if (preg_match('#/jobs/view/(?:[\w-]+-)?(\d+)#', $url, $im)) {
                $id = $im[1];
            }
            if ($title === '' || $url === '') {
                continue;
            }
            $cards[] = [
                'id' => $id !== '' ? $id : hash('sha256', $url),
                'title' => $title,
                'company' => $company,
                'location' => '',
                'url' => $url,
                'posted' => null,
                'snippet' => '',
            ];
            if (count($cards) >= self::PAGE_SIZE) {
                break;
            }
        }
        return $cards;
    }

    private static function xpathText(DOMXPath $xpath, DOMNode $ctx, string $query): string
    {
        $nodes = $xpath->query($query, $ctx);
        if ($nodes === false || $nodes->length === 0) {
            return '';
        }
        return trim(preg_replace('/\s+/u', ' ', $nodes->item(0)->textContent ?? '') ?? '');
    }

    private static function normalizeJobUrl(string $url): string
    {
        $url = html_entity_decode(trim($url), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($url === '') {
            return '';
        }
        if (str_starts_with($url, '/')) {
            $url = 'https://www.linkedin.com' . $url;
        }
        $url = preg_replace('/\?.*$/', '', $url) ?? $url;
        return $url;
    }

    private static function fetchDetailSnippet(string $jobId): string
    {
        if (!ctype_digit($jobId)) {
            return '';
        }
        $raw = JobHttp::get(self::DETAIL_URL . $jobId, self::headers(), 8);
        if ($raw === null || $raw === '') {
            return '';
        }
        return self::parseDetailSnippet($raw);
    }

    private static function parseDetailSnippet(string $raw): string
    {
        $prev = libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $ok = $dom->loadHTML('<?xml encoding="UTF-8">' . $raw, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        if (!$ok) {
            return mb_substr(trim(JobText::stripHtml($raw)), 0, 1200);
        }
        $xpath = new DOMXPath($dom);
        $desc = self::xpathText(
            $xpath,
            $dom,
            '//*[contains(@class,"description__text") or contains(@class,"show-more-less-html__markup")'
            . ' or contains(@class,"jobs-description-content__text")]'
        );
        if ($desc === '') {
            $desc = mb_substr(trim(JobText::stripHtml($raw)), 0, 1200);
        }
        return mb_substr($desc, 0, 4000);
    }

    /**
     * @param array{id:string,title:string,company:string,location:string,url:string,posted:?string,snippet:string} $card
     */
    private static function toListing(array $card, JobQuery $query): ?JobListing
    {
        $title = trim($card['title']);
        $url = trim($card['url']);
        if ($title === '' || $url === '') {
            return null;
        }

        $loc = trim($card['location']);
        $city = '';
        $bundesland = '';
        $country = '';
        if ($loc !== '') {
            $parts = array_values(array_filter(array_map('trim', explode(',', $loc)), static fn(string $p): bool => $p !== ''));
            $city = $parts[0] ?? '';
            if (count($parts) >= 3) {
                $bundesland = $parts[1];
                $country = $parts[count($parts) - 1];
            } elseif (count($parts) === 2) {
                if (JobText::looksLikeGermany('', '', $parts[1], '')) {
                    $country = $parts[1];
                } else {
                    $bundesland = $parts[1];
                }
            }
        }
        if ($country === '' && JobText::looksLikeGermany($city, $bundesland, '', $loc . ' Germany')) {
            $country = 'Germany';
        }
        if ($query->city !== '' && $city === '') {
            $city = $query->city;
        }
        if ($query->bundesland !== '' && $bundesland === '') {
            $bundesland = $query->bundesland;
        }

        // Germany-only gate.
        if (JobText::isForeignPrimaryLocation($city, $country !== '' ? $country : $loc, $title)) {
            return null;
        }
        if ($country !== '' && !JobText::looksLikeGermany($city, $bundesland, $country, $loc)
            && !preg_match('/\b(germany|deutschland|de)\b/iu', $country)) {
            return null;
        }
        if ($country === '' && $loc !== ''
            && !JobText::looksLikeGermany($city, $bundesland, '', $loc)
            && !preg_match('/\b(germany|deutschland|berlin|münchen|munich|hamburg|köln|cologne)\b/iu', $loc)) {
            return null;
        }
        if ($country === '') {
            $country = 'Germany';
        }

        $posted = $card['posted'];
        $company = trim($card['company']);
        $desc = trim($card['snippet']);
        $blob = $title . ' ' . $loc . ' ' . $desc;

        $job = new JobListing(
            'linkedin',
            $card['id'],
            $title,
            $company !== '' ? $company : 'LinkedIn',
            $city,
            $bundesland,
            $country,
            JobText::workMode($blob),
            JobText::employment($blob),
            'job',
            [],
            [],
            '',
            $posted,
            $url,
            $desc,
        );
        $job->applyUrl = $url;
        return $job;
    }
}
