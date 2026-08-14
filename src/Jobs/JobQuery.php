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

    public const SERP_BOARDS = ['linkedin', 'indeed', 'stepstone', 'xing', 'jobware'];

    /**
     * @param list<string> $sources
     */
    public function __construct(
        public string $q = '',
        public string $city = '',
        public string $bundesland = '',
        public int $radiusKm = 25,
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
        public int $postedDays = 0,
        public array $sources = ['arbeitsagentur'],
        public int $page = 1,
        public int $size = 25,
    ) {
        $this->sources = array_values(array_intersect(array_keys(self::SOURCES), $this->sources));
        if ($this->sources === []) {
            $this->sources = ['arbeitsagentur'];
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
        if (!in_array($this->postedDays, [1, 3, 7], true)) {
            $this->postedDays = 0;
        }
        $this->radiusKm = max(0, min(200, $this->radiusKm));
    }

    /** @param array<string, mixed> $get */
    public static function fromRequest(array $get): self
    {
        $sources = $get['sources'] ?? [];
        if (!is_array($sources)) {
            $sources = $sources !== '' ? [(string) $sources] : [];
        }
        $sources = array_map('strval', $sources);

        return new self(
            trim((string) ($get['q'] ?? '')),
            trim((string) ($get['city'] ?? '')),
            trim((string) ($get['bundesland'] ?? '')),
            (int) ($get['umkreis'] ?? 25),
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
            (int) ($get['posted'] ?? 0),
            $sources,
            (int) ($get['page'] ?? 1),
        );
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

    /** Extra keywords appended to free-text search. */
    public function extraKeywords(): string
    {
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
        if ($this->internship && $this->q === '') {
            $bits[] = 'Praktikum';
        }
        if ($this->wantsSource('university') && $this->q === '' && !$this->student && !$this->internship) {
            $bits[] = 'Werkstudent';
        }
        return implode(' ', $bits);
    }

    public function searchWas(): string
    {
        return trim($this->q . ' ' . $this->extraKeywords());
    }

    /** @param array<string, mixed> $overrides */
    public function toQuery(array $overrides = []): string
    {
        $data = [
            'search' => '1',
            'q' => $this->q,
            'city' => $this->city,
            'bundesland' => $this->bundesland,
            'umkreis' => $this->radiusKm,
            'work_mode' => $this->workMode,
            'employment' => $this->employment,
            'german_level' => $this->germanLevel,
            'posted' => $this->postedDays > 0 ? $this->postedDays : '',
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
        $data = array_merge($data, $overrides);
        $data = array_filter($data, static fn($v): bool => $v !== '' && $v !== null);
        return http_build_query($data);
    }

    public function cacheKey(): string
    {
        $payload = [
            'q' => $this->q,
            'city' => $this->city,
            'bundesland' => $this->bundesland,
            'umkreis' => $this->radiusKm,
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
            'posted' => $this->postedDays,
            'sources' => $this->sources,
        ];
        return 'search:' . hash('sha256', (string) json_encode($payload, JSON_UNESCAPED_UNICODE));
    }
}
