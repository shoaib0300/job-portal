# KaamMilo

PHP job-application portal (resume / cover letter / German job search).

## Setup

```bash
ddev start
ddev composer install   # PSR-4 autoload for src/
cp .env.example .env    # then set DATABASE_URL, optional BRIGHT_DATA_*
```

Site: `https://kaammilo.ddev.site` · portal: `/dashboard` or `https://portal.kaammilo.ddev.site`

## Layout

| Path | Role |
|------|------|
| `public/*.php` | Thin HTTP entry scripts |
| `src/Http/` | Controllers (Jobs first) |
| `src/Views/` | PHP templates |
| `src/Jobs/` | Job search domain (`KaamMilo\Jobs\…`) |
| `src/Jobs/Sources/` | Board adapters (Arbeitsagentur, LinkedIn guest, SERP, ATS, …) |
| `src/*.php` | Shared services still in the global namespace (`App`, `Auth`, `Db`, …) |

Composer maps `KaamMilo\` → `src/`. New Jobs code should use that namespace; add `use` imports in page scripts.

## Adding a job source

1. Create `src/Jobs/Sources/YourSource.php` in namespace `KaamMilo\Jobs\Sources`.
2. Implement `search(JobQuery $query): array` returning `listings` + notices.
3. Wire it in `KaamMilo\Jobs\JobAggregator::search`.
4. Register the source id/label in `JobQuery::SOURCES` if it should appear in filters.

Prefer parallel HTTP via `JobHttp::multiGet` / `multiPostJson` when a source hits several URLs.

## Jobs UX notes

- Search aggregates enabled sources, caches the full ranked list (`search:v11:…`), then paginates **20** cards.
- Prev/Next and Search refresh the results panel over AJAX (`?format=json`) with a loading spinner.
