<?php

declare(strict_types=1);

namespace App\Modules\ParentPortal\Http\Controllers;

use App\Models\User;
use App\Modules\Assessments\Actions\IssueReportCards;
use App\Modules\Assessments\Models\TermResult;
use App\Modules\ParentPortal\Http\Resources\TermResultResource;
use App\Modules\ParentPortal\Support\GuardianChildren;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * Résultats publiés (bulletins) : seuls les trimestres publiés existent
 * ici, la saisie en cours n'est jamais visible des parents.
 */
final class ChildResultsController
{
    public function index(Request $request, string $child): AnonymousResourceCollection
    {
        $student = GuardianChildren::findOrFail($this->user($request), $child);

        return TermResultResource::collection(
            TermResult::query()
                ->whereHas('enrollment', fn ($query) => $query->where('student_id', $student->id))
                ->with('term.schoolYear')
                ->latest('published_at')
                ->get(),
        );
    }

    public function show(Request $request, string $child, string $result): TermResultResource
    {
        return new TermResultResource($this->result($request, $child, $result)->load('subjectResults'));
    }

    /**
     * Bulletin PDF, identique à celui imprimé par l'école.
     */
    public function reportCard(Request $request, string $child, string $result, IssueReportCards $issueReportCards): Response
    {
        $termResult = $this->result($request, $child, $result)->load(['term', 'enrollment.schoolClass', 'enrollment.student']);

        $pdf = $issueReportCards->handle($termResult->term, $termResult->enrollment->schoolClass, [$termResult->enrollment_id]);
        $name = Str::slug('bulletin '.$termResult->enrollment->student->full_name.' '.$termResult->term->name);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$name.'.pdf"',
        ]);
    }

    private function result(Request $request, string $child, string $result): TermResult
    {
        $student = GuardianChildren::findOrFail($this->user($request), $child);

        return TermResult::query()
            ->where('uuid', $result)
            ->whereHas('enrollment', fn ($query) => $query->where('student_id', $student->id))
            ->firstOrFail();
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
