# KaamMilo

PHP job-application portal (resume / cover letter / German job search).

## Setup

```bash
ddev start
ddev composer dump-autoload   # PSR-4: KaamMilo\ + Freeworld\PhpJobspy\ (vendored)
# LinkedIn JobSpy (once per image, or after ddev restart with web-build Dockerfile):
ddev exec bash bin/install_jobspy.sh
cp .env.example .env    # DATABASE_URL; optional BRIGHT_DATA_* for Indeed/StepStone SERP
```

Site: `https://kaammilo.ddev.site` · portal: `/dashboard` or `https://portal.kaammilo.ddev.site`

## Layout

| Path | Role |
|------|------|
| `public/*.php` | Thin HTTP entry scripts |
| `src/Http/` | Controllers (Jobs first) |
| `src/Views/` | PHP templates |
| `src/Jobs/` | Job search domain (`KaamMilo\Jobs\…`) |
| `src/Jobs/Sources/` | Board adapters |
| `packages/php-jobspy/` | Vendored [alexseif/php-jobspy](https://github.com/alexseif/php-jobspy) (DTO + scripts) |
| `bin/jobspy_scrape.py` | LinkedIn scrape via `python-jobspy` |
| `src/*.php` | Shared services still global (`App`, `Auth`, `Db`, …) |

Composer maps `KaamMilo\` → `src/` and `Freeworld\PhpJobspy\` → `packages/php-jobspy/src/`.

## LinkedIn

LinkedIn search uses **php-jobspy / python-jobspy** (not Bright Data Marketplace, not the empty guest stub from datacenter IPs). Germany + max 7 days are applied in `LinkedInSource`.

## Adding a job source

1. Create `src/Jobs/Sources/YourSource.php` in namespace `KaamMilo\Jobs\Sources`.
2. Implement `search(JobQuery $query): array` returning `listings` + notices.
3. Wire it in `KaamMilo\Jobs\JobAggregator::search`.
4. Register the source id/label in `JobQuery::SOURCES` if it should appear in filters.

## Jobs UX notes

- Search aggregates enabled sources, caches the full ranked list (`search:v12:…`), then paginates **20** cards.
- Prev/Next and Search refresh the results panel over AJAX (`?format=json`) with a loading spinner.
