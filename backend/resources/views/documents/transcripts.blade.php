@php($num = fn ($value) => $value === null ? '—' : number_format((float) $value, 2, ',', ''))
@php($score = fn ($value) => rtrim(rtrim(number_format((float) $value, 2, ',', ''), '0'), ','))
@php($ordinal = fn (?int $rank) => $rank === null ? '—' : ($rank === 1 ? '1er' : $rank.'e'))
@extends('documents.layout')

@section('title', 'Relevés de notes — '.$term->name.' '.$term->schoolYear->name)

@section('styles')
    @page { margin: 12mm 12mm; }
    body { font-size: 8.8pt; }
    .school-name { font-size: 12.5pt; }
    .logo { width: 56px; height: 56px; }
    .transcript { page-break-after: always; }
    .transcript:last-child { page-break-after: auto; }
    h1 { text-align: center; font-size: 13pt; margin: 10px 0 2px; letter-spacing: 1px; }
    .subtitle { text-align: center; font-size: 9.5pt; margin-bottom: 8px; }
    table.identity { width: 100%; border: 1px solid #555; border-collapse: collapse; margin-bottom: 8px; }
    table.identity td { padding: 3px 6px; }
    table.marks { width: 100%; border-collapse: collapse; }
    table.marks th { background: #eee; font-size: 7.8pt; }
    table.marks th, table.marks td { border: 1px solid #777; padding: 3px 4px; vertical-align: top; }
    table.marks td.num, table.marks th.num { text-align: center; vertical-align: middle; }
    .mark { white-space: nowrap; }
    .muted { color: #666; font-size: 7.4pt; }
    table.summary { width: 100%; border-collapse: collapse; margin-top: 8px; }
    table.summary td { border: 1px solid #777; padding: 4px 6px; }
    .big { font-size: 12pt; font-weight: bold; }
    .signatures { width: 100%; margin-top: 16px; }
    .signatures td { width: 50%; text-align: center; font-size: 8pt; vertical-align: top; height: 55px; }
@endsection

@section('content')
    @foreach ($results as $result)
        @php($student = $result->enrollment->student)
        @php($studentScores = $scores->get($result->enrollment_id, collect()))
        <div class="transcript">
            @include('documents.partials.school-header')

            <h1>RELEVÉ DE NOTES</h1>
            <div class="subtitle">{{ $term->name }} — Année scolaire {{ $term->schoolYear->name }}</div>

            <table class="identity">
                <tr>
                    <td>Élève : <strong>{{ $student->full_name }}</strong></td>
                    <td>Matricule : <strong>{{ $student->registration_number }}</strong></td>
                    <td>Classe : <strong>{{ $result->class_name }}</strong></td>
                </tr>
                <tr>
                    <td>{{ ucfirst($student->gender->bornLabel()) }} le {{ $student->birth_date->format('d/m/Y') }}@if ($student->birth_place) à {{ $student->birth_place }}@endif</td>
                    <td>Effectif : {{ $result->class_size }}</td>
                    <td>Professeur principal : {{ $result->head_teacher_name ?? '—' }}</td>
                </tr>
            </table>

            <table class="marks">
                <thead>
                    <tr>
                        <th style="text-align: left; width: 22%;">Matière</th>
                        <th class="num" style="width: 6%;">Coef.</th>
                        <th style="text-align: left;">Notes des évaluations</th>
                        <th class="num" style="width: 9%;">Moy./{{ $grading->max_score }}</th>
                        <th class="num" style="width: 8%;">Points</th>
                        <th class="num" style="width: 7%;">Rang</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($result->subjectResults->sortBy('position') as $subject)
                        <tr>
                            <td><strong>{{ $subject->subject_name }}</strong>@if ($subject->teacher_name)<br><span class="muted">{{ $subject->teacher_name }}</span>@endif</td>
                            <td class="num">{{ rtrim(rtrim($subject->coefficient, '0'), '.') }}</td>
                            <td>
                                @forelse ($assessmentsBySubject->get($subject->class_subject_id, collect()) as $assessment)
                                    @php($mark = $studentScores->get($assessment->id))
                                    @php($label = match (true) {
                                        $mark === null => '—',
                                        $mark->status === \App\Modules\Assessments\Enums\ScoreStatus::Absent => 'Abs.',
                                        $mark->status === \App\Modules\Assessments\Enums\ScoreStatus::Exempted => 'Disp.',
                                        default => $score($mark->value).'/'.$score($assessment->max_score),
                                    })
                                    <span class="mark">{{ $assessment->title }} <span class="muted">({{ $assessment->held_on->format('d/m') }})</span> : <strong>{{ $label }}</strong></span>
                                    @if (! $loop->last)<br>@endif
                                @empty
                                    <span class="muted">Aucune évaluation</span>
                                @endforelse
                            </td>
                            <td class="num"><strong>{{ $num($subject->average) }}</strong></td>
                            <td class="num">{{ $subject->average === null ? '—' : $num((float) $subject->average * (float) $subject->coefficient) }}</td>
                            <td class="num">{{ $ordinal($subject->rank) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <table class="summary">
                <tr>
                    <td>Total des points : <strong>{{ $num($result->total_points) }}</strong> / coef. {{ rtrim(rtrim($result->total_coefficient, '0'), '.') }}</td>
                    <td>Moyenne générale : <span class="big">{{ $num($result->average) }}</span></td>
                    <td>Rang : <strong>{{ $ordinal($result->rank) }}{{ $result->rank_tied ? ' ex æquo' : '' }}</strong> / {{ $result->class_size }}</td>
                    <td>Mention : <strong>{{ $result->distinction ?? '—' }}</strong></td>
                </tr>
            </table>

            <table class="signatures">
                <tr>
                    <td>Le professeur principal</td>
                    <td>{{ $settings?->city ? 'Fait à '.$settings->city.', le ' : 'Le ' }}{{ $result->published_at->format('d/m/Y') }}<br>Le chef d'établissement @if ($settings?->director_name)<br><strong>{{ $settings->director_name }}</strong>@endif</td>
                </tr>
            </table>
        </div>
    @endforeach
@endsection
