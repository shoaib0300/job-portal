<?php

declare(strict_types=1);

namespace KaamMilo\Jobs;

use App;


final class JobListing
{
    /**
     * @param list<string> $seniorityTags
     * @param list<string> $languages
     */
    public function __construct(
        public string $source,
        public string $externalId,
        public string $title,
        public string $company,
        public string $city = '',
        public string $bundesland = '',
        public string $country = 'Germany',
        public string $workMode = 'unknown',
        public string $employment = 'unknown',
        public string $offerType = 'unknown',
        public array $seniorityTags = [],
        public array $languages = [],
        public string $salaryText = '',
        public ?string $postedAt = null,
        public string $url = '',
        public string $description = '',
        public string $fingerprint = '',
        public string $applyUrl = '',
    ) {
        if ($this->fingerprint === '') {
            $this->fingerprint = self::makeFingerprint($this->company, $this->title, $this->city);
        }
    }

    public function applyHref(): string
    {
        // Employer apply link only — never fall back to BA listing as "employer website".
        if (trim($this->applyUrl) !== '') {
            return App::normalizeHttpUrl(trim($this->applyUrl));
        }
        if ($this->source === 'arbeitsagentur') {
            return '';
        }
        return App::normalizeHttpUrl(trim($this->url));
    }

    public function listingHref(): string
    {
        return App::normalizeHttpUrl(trim($this->url));
    }

    public function listingUrlDiffers(): bool
    {
        $apply = $this->applyHref();
        $listing = $this->listingHref();
        return $listing !== '' && $apply !== '' && $listing !== $apply;
    }

    public static function makeFingerprint(string $company, string $title, string $city): string
    {
        $norm = static function (string $value): string {
            $value = mb_strtolower(trim($value));
            $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
            return $value;
        };
        return $norm($company) . '|' . $norm($title) . '|' . $norm($city);
    }

    /** Cross-platform identity: same company + title + posted date → same job. */
    public static function contentKey(string $company, string $title, ?string $postedAt): string
    {
        $norm = static function (string $value): string {
            $value = mb_strtolower(trim($value));
            $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
            return $value;
        };
        $posted = '';
        if ($postedAt !== null && $postedAt !== '') {
            $posted = substr($postedAt, 0, 10);
        }
        return hash('sha256', $norm($company) . '|' . $norm($title) . '|' . $posted);
    }

    public function locationLine(): string
    {
        $parts = array_filter([$this->city, $this->bundesland, $this->country], static fn(string $p): bool => $p !== '');
        if ($parts === []) {
            return $this->workMode === 'remote' ? 'Remote, Germany' : 'Germany';
        }
        return implode(', ', $parts);
    }

    public function cacheKey(): string
    {
        return 'job:' . $this->source . ':' . $this->externalId;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'source' => $this->source,
            'external_id' => $this->externalId,
            'title' => $this->title,
            'company' => $this->company,
            'city' => $this->city,
            'bundesland' => $this->bundesland,
            'country' => $this->country,
            'work_mode' => $this->workMode,
            'employment' => $this->employment,
            'offer_type' => $this->offerType,
            'seniority_tags' => $this->seniorityTags,
            'languages' => $this->languages,
            'salary_text' => $this->salaryText,
            'posted_at' => $this->postedAt,
            'url' => $this->url,
            'apply_url' => $this->applyUrl,
            'description' => $this->description,
            'fingerprint' => $this->fingerprint,
        ];
    }

    /** @param array<string, mixed> $row */
    public static function fromArray(array $row): self
    {
        $tags = $row['seniority_tags'] ?? [];
        $langs = $row['languages'] ?? [];
        return new self(
            (string) ($row['source'] ?? ''),
            (string) ($row['external_id'] ?? ''),
            (string) ($row['title'] ?? ''),
            (string) ($row['company'] ?? ''),
            (string) ($row['city'] ?? ''),
            (string) ($row['bundesland'] ?? ''),
            (string) ($row['country'] ?? 'Germany'),
            (string) ($row['work_mode'] ?? 'unknown'),
            (string) ($row['employment'] ?? 'unknown'),
            (string) ($row['offer_type'] ?? 'unknown'),
            is_array($tags) ? array_values(array_map('strval', $tags)) : [],
            is_array($langs) ? array_values(array_map('strval', $langs)) : [],
            (string) ($row['salary_text'] ?? ''),
            isset($row['posted_at']) && $row['posted_at'] !== '' && $row['posted_at'] !== null
                ? (string) $row['posted_at']
                : null,
            (string) ($row['url'] ?? ''),
            (string) ($row['description'] ?? ''),
            (string) ($row['fingerprint'] ?? ''),
            (string) ($row['apply_url'] ?? ''),
        );
    }
}
