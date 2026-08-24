<?php

declare(strict_types=1);

final class ArbeitsagenturSource
{
    private const SEARCH = 'https://rest.arbeitsagentur.de/jobboerse/jobsuche-service/pc/v6/jobs';
    private const DETAILS = 'https://rest.arbeitsagentur.de/jobboerse/jobsuche-service/pc/v4/jobdetails/';
    private const KEY = 'jobboerse-jobsuche';

    public function id(): string
    {
        return 'arbeitsagentur';
    }

    public function label(): string
    {
        return 'Bundesagentur für Arbeit';
    }

    /**
     * @return array{listings: list<JobListing>, notice: ?string}
     */
    public function search(JobQuery $query): array
    {
        $params = [
            'was' => $query->searchWas(),
            'wo' => $query->whereText(),
            'page' => 1,
            'size' => 50,
            'zeitarbeit' => 'false',
        ];
        if ($params['was'] === '') {
            unset($params['was']);
        }
        if ($params['wo'] === '') {
            unset($params['wo']);
        }
        if ($query->postedDays > 0) {
            $params['veroeffentlichtseit'] = $query->postedDays;
        }
        if ($query->internship) {
            $params['angebotsart'] = 34;
        } else {
            $params['angebotsart'] = 1;
        }
        $zeit = [];
        if ($query->employment === 'fulltime') {
            $zeit[] = 'vz';
        }
        if ($query->employment === 'parttime') {
            $zeit[] = 'tz';
        }
        if ($query->workMode === 'remote') {
            $zeit[] = 'ho';
        }
        if ($zeit !== []) {
            $params['arbeitszeit'] = implode(';', $zeit);
        }

        $url = self::SEARCH . '?' . http_build_query($params);
        $data = JobHttp::getJson($url, ['X-API-Key: ' . self::KEY], 14);
        if ($data === null) {
            return ['listings' => [], 'notice' => 'Arbeitsagentur did not respond. Try again in a moment.'];
        }

        $rows = $data['ergebnisliste'] ?? $data['stellenangebote'] ?? [];
        if (!is_array($rows)) {
            $rows = [];
        }
        $listings = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $job = $this->fromSearchRow($row);
            if ($job === null) {
                continue;
            }
            if ($query->internship) {
                $job->offerType = 'internship';
            }
            if ($query->workMode === 'remote') {
                $job->workMode = 'remote';
            }
            if ($query->employment === 'fulltime') {
                $job->employment = 'fulltime';
            } elseif ($query->employment === 'parttime') {
                $job->employment = 'parttime';
            }
            $listings[] = JobText::enrich($job);
        }
        return ['listings' => $listings, 'notice' => null];
    }

    public function details(string $externalId): ?JobListing
    {
        $cached = JobCache::getListing('arbeitsagentur', $externalId);
        $code = base64_encode($externalId);
        $data = JobHttp::getJson(self::DETAILS . rawurlencode($code), ['X-API-Key: ' . self::KEY], 14);
        if ($data === null) {
            return $cached;
        }
        $job = $this->fromDetails($data, $cached);
        JobCache::putListing($job);
        return JobText::enrich($job);
    }

    /** @param array<string, mixed> $row */
    private function fromSearchRow(array $row): ?JobListing
    {
        $ref = (string) ($row['refnr'] ?? $row['referenznummer'] ?? '');
        if ($ref === '') {
            return null;
        }
        $ort = $this->placeFromRow($row);
        $hash = (string) ($row['hashId'] ?? '');
        $urls = $this->resolveUrls($row, $ref, $hash);
        $posted = $this->postedFromRow($row);
        $title = (string) ($row['stellenangebotsTitel'] ?? $row['beruf'] ?? $row['titel'] ?? 'Stelle');
        $company = (string) ($row['firma'] ?? $row['arbeitgeber'] ?? '');
        $offerHint = (string) ($row['stellenangebotsart'] ?? $row['angebotsart'] ?? '');
        $fulltime = $row['arbeitszeitVollzeit'] ?? null;
        $employment = 'unknown';
        if ($fulltime === true) {
            $employment = 'fulltime';
        } elseif ($fulltime === false) {
            $employment = 'parttime';
        }

        return new JobListing(
            'arbeitsagentur',
            $ref,
            $title,
            $company,
            $ort['ort'],
            $this->prettyRegion($ort['region']),
            $this->prettyCountry($ort['land']),
            'unknown',
            $employment,
            JobText::offerType($title, $offerHint),
            [],
            [],
            '',
            $posted,
            $urls['url'],
            '',
            '',
            $urls['applyUrl'],
        );
    }

    /** @param array<string, mixed> $data */
    private function fromDetails(array $data, ?JobListing $fallback): JobListing
    {
        $ref = (string) ($data['refnr'] ?? $data['referenznummer'] ?? ($fallback?->externalId ?? ''));
        $title = (string) ($data['titel'] ?? $data['stellenangebotsTitel'] ?? ($fallback?->title ?? 'Stelle'));
        $company = (string) ($data['arbeitgeber'] ?? $data['firma'] ?? ($fallback?->company ?? ''));
        $partnerName = trim((string) ($data['allianzpartnerName'] ?? ''));
        if ($company === '' && $partnerName !== '') {
            $company = $partnerName;
        }
        $ort = $this->placeFromRow($data);
        $descRaw = (string) ($data['stellenbeschreibung'] ?? $data['stellenangebotsBeschreibung'] ?? '');
        $desc = JobText::stripHtml($descRaw);
        $zeit = $data['arbeitszeitmodelle'] ?? [];
        $zeitHint = is_array($zeit) && isset($zeit[0]) ? (string) $zeit[0] : '';
        $offerHint = (string) ($data['angebotsart'] ?? $data['stellenangebotsart'] ?? '');
        $hash = (string) ($data['hashId'] ?? '');
        $urls = $this->resolveUrls($data, $ref, $hash);
        $url = $urls['url'];
        $applyUrl = $urls['applyUrl'];
        if ($url === '' && $fallback !== null) {
            $url = $fallback->url;
        }
        if ($applyUrl === '' && $fallback !== null) {
            $applyUrl = $fallback->applyUrl;
        }
        $posted = $this->postedFromRow($data) ?? ($fallback?->postedAt ?? null);
        $salary = (string) ($data['verguetung'] ?? $data['verguetungsangabe'] ?? '');
        if ($salary === 'KEINE_ANGABEN') {
            $salary = '';
        }
        $fulltime = $data['arbeitszeitVollzeit'] ?? null;
        $employment = JobText::employment($title . ' ' . $desc, $zeitHint);
        if ($fulltime === true) {
            $employment = 'fulltime';
        } elseif ($fulltime === false) {
            $employment = 'parttime';
        }

        return new JobListing(
            'arbeitsagentur',
            $ref !== '' ? $ref : (string) ($fallback?->externalId ?? ''),
            $title,
            $company,
            $ort['ort'] !== '' ? $ort['ort'] : (string) ($fallback?->city ?? ''),
            $this->prettyRegion($ort['region'] !== '' ? $ort['region'] : (string) ($fallback?->bundesland ?? '')),
            $this->prettyCountry($ort['land'] !== '' ? $ort['land'] : (string) ($fallback?->country ?? 'Germany')),
            JobText::workMode($title . ' ' . $desc, $zeitHint),
            $employment,
            JobText::offerType($title . ' ' . $desc, $offerHint),
            JobText::seniorityTags($title . ' ' . $desc),
            JobText::languages($title . "\n" . $desc),
            $salary,
            $posted,
            $url,
            $desc,
            '',
            $applyUrl,
        );
    }

    /**
     * @param array<string, mixed> $row
     * @return array{ort:string,region:string,land:string}
     */
    private function placeFromRow(array $row): array
    {
        $ort = ['ort' => '', 'region' => '', 'land' => ''];
        if (isset($row['arbeitsort']) && is_array($row['arbeitsort'])) {
            $ort['ort'] = (string) ($row['arbeitsort']['ort'] ?? '');
            $ort['region'] = (string) ($row['arbeitsort']['region'] ?? '');
            $ort['land'] = (string) ($row['arbeitsort']['land'] ?? '');
            return $ort;
        }
        $locs = $row['stellenlokationen'] ?? $row['arbeitsorte'] ?? [];
        if (is_array($locs) && isset($locs[0]) && is_array($locs[0])) {
            $first = $locs[0];
            $addr = isset($first['adresse']) && is_array($first['adresse']) ? $first['adresse'] : $first;
            $ort['ort'] = (string) ($addr['ort'] ?? '');
            $ort['region'] = (string) ($addr['region'] ?? '');
            $ort['land'] = (string) ($addr['land'] ?? '');
        }
        return $ort;
    }

    /**
     * Listing = Arbeitsagentur job page.
     * Apply = only the company/ATS job page (externeURL or a job URL in the text).
     * Generic partner homepages are not used for "Apply on employer website".
     *
     * @param array<string, mixed> $row
     * @return array{url:string,applyUrl:string}
     */
    private function resolveUrls(array $row, string $ref, string $hash): array
    {
        $externe = App::normalizeHttpUrl(self::stringField($row, 'externeURL', 'externeUrl'));
        $partner = App::normalizeHttpUrl(self::stringField($row, 'allianzpartnerUrl', 'allianzpartnerURL'));
        $darstellung = App::normalizeHttpUrl(self::stringField($row, 'arbeitgeberdarstellungUrl', 'arbeitgeberdarstellungURL'));
        $descBlob = self::stringField($row, 'stellenbeschreibung', 'stellenangebotsBeschreibung');
        $fromDesc = self::careerUrlFromText($descBlob);

        $listing = '';
        if ($hash !== '') {
            $listing = 'https://www.arbeitsagentur.de/jobsuche/jobdetail/' . rawurlencode($hash);
        } elseif ($ref !== '') {
            $listing = 'https://www.arbeitsagentur.de/jobsuche/jobdetail/' . rawurlencode($ref);
        }

        $apply = '';
        if ($externe !== '') {
            $apply = $externe;
        } elseif ($fromDesc !== '') {
            $apply = $fromDesc;
        } elseif (self::looksLikeJobPage($partner)) {
            $apply = $partner;
        } elseif (self::looksLikeJobPage($darstellung)) {
            $apply = $darstellung;
        }
        $apply = self::preferDirectApplyPage($apply);

        if ($listing === '' && $apply !== '') {
            $listing = $apply;
        }

        return ['url' => $listing, 'applyUrl' => $apply];
    }

    /** True for ATS / career job pages; false for bare company homepages. */
    private static function looksLikeJobPage(string $url): bool
    {
        if ($url === '') {
            return false;
        }
        $path = (string) (parse_url($url, PHP_URL_PATH) ?? '');
        if ($path === '' || $path === '/') {
            return false;
        }
        return (bool) preg_match(
            '#(jobs?\.personio|/job/|/jobs/|karriere|career|stellen|bewerb|apply|recruit|greenhouse|softgarden|workday|personio|stepstone|indeed|xing\.com/jobs)#iu',
            $url
        );
    }

    /** @param array<string, mixed> $row */
    private static function stringField(array $row, string ...$keys): string
    {
        foreach ($keys as $key) {
            $v = trim((string) ($row[$key] ?? ''));
            if ($v !== '') {
                return $v;
            }
        }
        return '';
    }

    /** Personio job pages apply at /job/{id}/apply. */
    private static function preferDirectApplyPage(string $url): string
    {
        if ($url === '') {
            return '';
        }
        if (preg_match('#^(https://[a-z0-9.-]+\.jobs\.personio\.de/job/\d+)/?(?:apply)?/?$#i', $url, $m)) {
            return $m[1] . '/apply';
        }
        return $url;
    }

    /** Prefer career / jobs / bewerbung links found in free text or HTML. */
    private static function careerUrlFromText(string $text): string
    {
        if ($text === '' || !preg_match_all('#https?://[^\s<>"\']+|www\.[^\s<>"\']+#iu', $text, $matches)) {
            return '';
        }
        $scored = [];
        foreach ($matches[0] as $raw) {
            $url = App::normalizeHttpUrl(rtrim((string) $raw, '.,);]»"\''));
            if ($url === '') {
                continue;
            }
            $low = mb_strtolower($url);
            if (str_contains($low, 'arbeitsagentur.de')) {
                continue;
            }
            $score = 0;
            if (preg_match('#/(karriere|career|jobs?|stellen|bewerb|apply|recruit|vacanc)#u', $low)) {
                $score += 6;
            }
            if (preg_match('#(karriere|bewerbung|stellenangebot)#u', $low)) {
                $score += 3;
            }
            if ($score > 0) {
                $scored[$url] = max($scored[$url] ?? 0, $score);
            }
        }
        if ($scored === []) {
            return '';
        }
        arsort($scored);
        return (string) array_key_first($scored);
    }

    /** @param array<string, mixed> $row */
    private function postedFromRow(array $row): ?string
    {
        $direct = (string) ($row['aktuelleVeroeffentlichungsdatum'] ?? $row['datumErsteVeroeffentlichung'] ?? $row['ersteVeroeffentlichungsdatum'] ?? '');
        if ($direct !== '') {
            return substr($direct, 0, 10);
        }
        $span = $row['veroeffentlichungszeitraum'] ?? null;
        if (is_array($span) && !empty($span['von'])) {
            return substr((string) $span['von'], 0, 10);
        }
        return null;
    }

    private function prettyRegion(string $region): string
    {
        $map = [
            'BADEN-WUERTTEMBERG' => 'Baden-Württemberg',
            'BAYERN' => 'Bayern',
            'BERLIN' => 'Berlin',
            'BRANDENBURG' => 'Brandenburg',
            'BREMEN' => 'Bremen',
            'HAMBURG' => 'Hamburg',
            'HESSEN' => 'Hessen',
            'MECKLENBURG-VORPOMMERN' => 'Mecklenburg-Vorpommern',
            'NIEDERSACHSEN' => 'Niedersachsen',
            'NORDRHEIN-WESTFALEN' => 'Nordrhein-Westfalen',
            'RHEINLAND-PFALZ' => 'Rheinland-Pfalz',
            'SAARLAND' => 'Saarland',
            'SACHSEN' => 'Sachsen',
            'SACHSEN-ANHALT' => 'Sachsen-Anhalt',
            'SCHLESWIG-HOLSTEIN' => 'Schleswig-Holstein',
            'THUERINGEN' => 'Thüringen',
        ];
        $key = strtoupper(str_replace(['ü', 'ä', 'ö'], ['UE', 'AE', 'OE'], $region));
        return $map[$key] ?? $region;
    }

    private function prettyCountry(string $land): string
    {
        if ($land === '' || strcasecmp($land, 'DEUTSCHLAND') === 0 || strcasecmp($land, 'Germany') === 0) {
            return 'Germany';
        }
        return $land;
    }
}
