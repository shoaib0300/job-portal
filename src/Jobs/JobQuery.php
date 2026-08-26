<?php

declare(strict_types=1);

final class JobQuery
{
    public const SOURCES = [
        'arbeitsagentur' => 'Bundesagentur für Arbeit',
        'linkedin' => 'LinkedIn',
        'indeed' => 'Indeed',
        'stepstone' => 'StepStone',
        'xing' => 'XING',
        'jobware' => 'Jobware',
        'glassdoor' => 'Glassdoor',
        'jobexport' => 'Jobexport',
        'career' => 'Company career pages',
        'university' => 'University / student portals',
        'public_sector' => 'Public-sector (Interamt)',
    ];

    public const BUNDESLAENDER = [
        'Baden-Württemberg',
        'Bayern',
        'Berlin',
        'Brandenburg',
        'Bremen',
        'Hamburg',
        'Hessen',
        'Mecklenburg-Vorpommern',
        'Niedersachsen',
        'Nordrhein-Westfalen',
        'Rheinland-Pfalz',
        'Saarland',
        'Sachsen',
        'Sachsen-Anhalt',
        'Schleswig-Holstein',
        'Thüringen',
    ];

    public const SERP_BOARDS = ['linkedin', 'indeed', 'stepstone', 'xing', 'jobware', 'glassdoor'];

    /** Built-in defaults until the user saves a different Sources selection. */
    public const DEFAULT_SOURCES = ['arbeitsagentur', 'jobexport', 'career'];

    private const FILTERS_SETTING = 'job_filters';

    /**
     * @param list<string> $keywords
     * @param list<string> $sources
     */
    public function __construct(
        public string $q = '',
        public string $city = '',
        public string $bundesland = '',
        public string $workMode = '',
        public bool $student = false,
        public bool $junior = false,
        public bool $graduate = false,
        public bool $internship = false,
        public bool $noExperience = false,
        public string $employment = '',
        public bool $english = false,
        public string $germanLevel = '',
        public bool $hasSalary = false,
        public bool $matchResume = false,
        public int $postedDays = 0,
        public string $sort = 'relevance',
        public array $sources = ['arbeitsagentur', 'jobexport'],
        public int $page = 1,
        public int $size = 25,
        public array $keywords = [],
    ) {
        $this->keywords = self::normalizeKeywords(
            $this->keywords !== [] ? $this->keywords : self::parseKeywords($this->q)
        );
        $this->q = implode(', ', $this->keywords);
        $this->sources = array_values(array_intersect(array_keys(self::SOURCES), $this->sources));
        if ($this->sources === []) {
            $this->sources = self::DEFAULT_SOURCES;
        }
        $this->page = max(1, $this->page);
        $this->size = min(50, max(10, $this->size));
        if (!in_array($this->bundesland, self::BUNDESLAENDER, true)) {
            $this->bundesland = '';
        }
        if (!in_array($this->workMode, ['remote', 'hybrid', 'onsite'], true)) {
            $this->workMode = '';
        }
        if (!in_array($this->employment, ['fulltime', 'parttime'], true)) {
            $this->employment = '';
        }
        if (!in_array($this->germanLevel, ['A1', 'A2', 'B1', 'B2', 'C1'], true)) {
            $this->germanLevel = '';
        }
        if (!in_array($this->postedDays, [1, 7, 14], true)) {
            $this->postedDays = 0;
        }
        if (!in_array($this->sort, ['relevance', 'recent'], true)) {
            $this->sort = 'relevance';
        }
    }

    /** @param mixed $raw */
    public static function parseKeywords(mixed $raw): array
    {
        $items = [];
        if (is_array($raw)) {
            foreach ($raw as $v) {
                foreach (self::parseKeywords($v) as $part) {
                    $items[] = $part;
                }
            }
            return self::normalizeKeywords($items);
        }
        $s = trim((string) $raw);
        if ($s === '') {
            return [];
        }
        foreach (preg_split('/\s*,\s*/u', $s) ?: [] as $part) {
            $part = trim($part);
            if ($part !== '') {
                $items[] = $part;
            }
        }
        return self::normalizeKeywords($items);
    }

    /** @param list<string> $items @return list<string> */
    public static function normalizeKeywords(array $items): array
    {
        $out = [];
        $seen = [];
        foreach ($items as $item) {
            $item = trim((string) $item);
            if ($item === '') {
                continue;
            }
            $key = mb_strtolower($item);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $item;
            if (count($out) >= 12) {
                break;
            }
        }
        return $out;
    }

    /** @return list<string> */
    public static function defaultSources(): array
    {
        return self::DEFAULT_SOURCES;
    }

    /** Saved last search query string (without leading ?), or empty. */
    public static function savedFiltersQuery(): string
    {
        return trim((string) (App::setting(self::FILTERS_SETTING, '') ?: ''));
    }

    public static function saveFilters(self $query): void
    {
        App::setSetting(self::FILTERS_SETTING, $query->toQuery(['page' => 1]));
    }

    public static function clearSavedFilters(): void
    {
        App::setSetting(self::FILTERS_SETTING, '');
    }

    /**
     * Load GET params, merging saved filters when the URL has no search state.
     *
     * @param array<string, mixed> $get
     * @return array<string, mixed>
     */
    public static function mergeRequest(array $get): array
    {
        if (isset($get['reset'])) {
            return $get;
        }
        if (isset($get['search']) || isset($get['sources']) || isset($get['q']) || isset($get['q_add'])
            || trim((string) ($get['city'] ?? '')) !== ''
            || trim((string) ($get['bundesland'] ?? '')) !== '') {
            return $get;
        }
        $saved = self::savedFiltersQuery();
        if ($saved === '') {
            return $get;
        }
        $restored = [];
        parse_str($saved, $restored);
        return is_array($restored) ? $restored : $get;
    }

    /** @param array<string, mixed> $get */
    public static function fromRequest(array $get): self
    {
        if (array_key_exists('sources', $get)) {
            $sources = $get['sources'];
            if (!is_array($sources)) {
                $sources = $sources !== '' ? [(string) $sources] : [];
            }
            $sources = array_map('strval', $sources);
        } else {
            $sources = self::DEFAULT_SOURCES;
        }

        $rawQ = $get['q'] ?? ($get['keywords'] ?? []);
        $keywords = self::normalizeKeywords(array_merge(
            self::parseKeywords($rawQ),
            self::parseKeywords($get['q_add'] ?? '')
        ));
        $matchResume = isset($get['match_resume']);
        if ($matchResume && $keywords === []) {
            $keywords = self::normalizeKeywords(ResumeJobMatch::searchKeywords());
        }

        return new self(
            implode(', ', $keywords),
            trim((string) ($get['city'] ?? '')),
            trim((string) ($get['bundesland'] ?? '')),
            (string) ($get['work_mode'] ?? ''),
            isset($get['student']),
            isset($get['junior']),
            isset($get['graduate']),
            isset($get['internship']),
            isset($get['no_experience']),
            (string) ($get['employment'] ?? ''),
            isset($get['english']),
            (string) ($get['german_level'] ?? ''),
            isset($get['has_salary']),
            $matchResume,
            (int) ($get['posted'] ?? 0),
            (string) ($get['sort'] ?? 'relevance'),
            $sources,
            (int) ($get['page'] ?? 1),
            25,
            $keywords,
        );
    }

    /** Jobs index URL that restores the last saved filter (or a clean page). */
    public static function jobsHref(): string
    {
        $saved = self::savedFiltersQuery();
        return $saved !== '' ? '/jobs.php?' . $saved : '/jobs.php';
    }

    public function wantsSource(string $id): bool
    {
        return in_array($id, $this->sources, true);
    }

    public function whereText(): string
    {
        if ($this->city !== '') {
            return $this->city;
        }
        return $this->bundesland;
    }

    public function hasKeywords(): bool
    {
        return $this->keywords !== [];
    }

    /** Extra keywords appended to free-text search only when the user typed no roles. */
    public function extraKeywords(): string
    {
        // Level checkboxes filter results after fetch. Do not AND them into the API query
        // when the user already has role keywords (that made BA/Jobexport return 0 hits).
        if ($this->hasKeywords()) {
            return '';
        }
        $bits = [];
        if ($this->student) {
            $bits[] = 'Werkstudent';
        }
        if ($this->junior) {
            $bits[] = 'Junior';
        }
        if ($this->graduate) {
            $bits[] = 'Absolvent';
        }
        if ($this->noExperience) {
            $bits[] = 'Berufseinsteiger';
        }
        if ($this->internship) {
            $bits[] = 'Praktikum';
        }
        if ($bits === [] && $this->wantsSource('university')) {
            $bits[] = 'Werkstudent';
        }
        return implode(' ', $bits);
    }

    public function hasLevelFilter(): bool
    {
        return $this->student || $this->junior || $this->graduate || $this->internship || $this->noExperience;
    }

    /** Space-joined roles for APIs that take one was= string (Arbeitsagentur). */
    public function searchWas(): string
    {
        $roles = implode(' ', $this->keywords);
        return trim($roles . ' ' . $this->extraKeywords());
    }

    /**
     * Google-friendly role clause: one phrase, or (A OR B OR C) for multiple.
     * Phrases with spaces are quoted.
     */
    public function serpWas(): string
    {
        $roles = [];
        foreach ($this->keywords as $kw) {
            $roles[] = preg_match('/\s/u', $kw) ? '"' . $kw . '"' : $kw;
        }
        $rolePart = '';
        if (count($roles) === 1) {
            $rolePart = $roles[0];
        } elseif (count($roles) > 1) {
            $rolePart = '(' . implode(' OR ', $roles) . ')';
        }
        return trim($rolePart . ' ' . $this->extraKeywords());
    }

    /** @param array<string, mixed> $overrides */
    public function toQuery(array $overrides = []): string
    {
        $data = [
            'search' => '1',
            'q' => $this->keywords,
            'city' => $this->city,
            'bundesland' => $this->bundesland,
            'work_mode' => $this->workMode,
            'employment' => $this->employment,
            'german_level' => $this->germanLevel,
            'posted' => $this->postedDays > 0 ? $this->postedDays : '',
            'sort' => $this->sort !== 'relevance' ? $this->sort : '',
            'page' => $this->page,
            'sources' => $this->sources,
        ];
        if ($this->student) {
            $data['student'] = '1';
        }
        if ($this->junior) {
            $data['junior'] = '1';
        }
        if ($this->graduate) {
            $data['graduate'] = '1';
        }
        if ($this->internship) {
            $data['internship'] = '1';
        }
        if ($this->noExperience) {
            $data['no_experience'] = '1';
        }
        if ($this->english) {
            $data['english'] = '1';
        }
        if ($this->hasSalary) {
            $data['has_salary'] = '1';
        }
        if ($this->matchResume) {
            $data['match_resume'] = '1';
        }
        $data = array_merge($data, $overrides);
        $data = array_filter(
            $data,
            static fn($v): bool => $v !== '' && $v !== null && $v !== []
        );
        return http_build_query($data);
    }

    public function cacheKey(): string
    {
        $payload = [
            'keywords' => $this->keywords,
            'city' => $this->city,
            'bundesland' => $this->bundesland,
            'work_mode' => $this->workMode,
            'student' => $this->student,
            'junior' => $this->junior,
            'graduate' => $this->graduate,
            'internship' => $this->internship,
            'no_experience' => $this->noExperience,
            'employment' => $this->employment,
            'english' => $this->english,
            'german_level' => $this->germanLevel,
            'has_salary' => $this->hasSalary,
            'match_resume' => $this->matchResume,
            'posted' => $this->postedDays,
            'sort' => $this->sort,
            'sources' => $this->sources,
        ];
        return 'search:v5:' . hash('sha256', (string) json_encode($payload, JSON_UNESCAPED_UNICODE));
    }
}
