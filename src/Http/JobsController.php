<?php

declare(strict_types=1);

namespace KaamMilo\Http;

use App;
use Auth;
use KaamMilo\Jobs\CareerCompanies;
use KaamMilo\Jobs\JobAggregator;
use KaamMilo\Jobs\JobQuery;
use KaamMilo\Jobs\JobsResultsView;
use KaamMilo\Jobs\ResumeJobMatch;
use KaamMilo\Jobs\Sources\SerpBoardSource;

final class JobsController
{
    public function index(): void
    {
        JobAggregator::ensureSchema();

        if (isset($_GET['reset'])) {
            JobQuery::clearSavedFilters();
            App::redirect('/jobs');
        }

        $get = JobQuery::mergeRequest($_GET);
        $query = JobQuery::fromRequest($get);
        $ran = isset($get['search']) || $query->hasKeywords() || $query->city !== '' || $query->bundesland !== ''
            || $query->hasLevelFilter() || $query->matchResume;
        if (isset($_GET['search'])) {
            JobQuery::saveFilters($query);
        }

        $result = [
            'listings' => [],
            'total' => 0,
            'notices' => [],
            'page' => 1,
            'pages' => 1,
        ];
        if ($ran) {
            $result = JobAggregator::search($query);
        }

        if (isset($_GET['format']) && (string) $_GET['format'] === 'json') {
            $this->json($query, $result, $ran);
            return;
        }

        View::renderToLayout('Jobs', 'jobs/index', [
            'query' => $query,
            'result' => $result,
            'ran' => $ran,
            'sourceLabels' => JobQuery::SOURCES,
            'companyOptions' => CareerCompanies::filterOptions(Auth::id()),
            'postedOptions' => [
                1 => 'Today · 24h',
                14 => 'Last 14 days (max)',
            ],
            'resumeTitle' => ResumeJobMatch::activeTitle(),
            'serpConfigured' => SerpBoardSource::configured(),
            'resultsHtml' => JobsResultsView::render($query, $result, $ran),
        ]);
    }

    /**
     * @param array{listings: list<\KaamMilo\Jobs\JobListing>, total: int, notices: list<string>, page: int, pages: int} $result
     */
    private function json(JobQuery $query, array $result, bool $ran): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode([
            'ok' => true,
            'html' => JobsResultsView::render($query, $result, $ran),
            'page' => (int) $result['page'],
            'pages' => (int) $result['pages'],
            'total' => (int) $result['total'],
            'ran' => $ran,
            'query' => $query->toQuery(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
