<?php

declare(strict_types=1);

namespace KaamFit\Jobs;

use KaamFit\Http\View;
use KaamFit\Jobs\ResumeJobMatch;

/**
 * Renders the jobs results panel HTML (meta, notices, cards, pagination).
 */
final class JobsResultsView
{
    /**
     * @param array{listings: list<JobListing>, total: int, notices: list<string>, page: int, pages: int} $result
     * @param array<string, array{status:string,id:int}> $applicationMap
     */
    public static function render(JobQuery $query, array $result, bool $ran, array $applicationMap = []): string
    {
        return View::render('jobs/results', [
            'query' => $query,
            'result' => $result,
            'ran' => $ran,
            'sourceLabels' => JobQuery::SOURCES,
            'resumeTitle' => ResumeJobMatch::masterTitle(),
            'resumeTerms' => $query->matchResume ? ResumeJobMatch::scoreTerms() : [],
            'applicationMap' => $applicationMap,
        ]);
    }
}
