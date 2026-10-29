@php($num = fn ($value) => $value === null ? '—' : number_format((float) $value, 2, ',', ''))
@php($ordinal = fn (?int $rank) => $rank === null ? '—' : ($rank === 1 ? '1er' : $rank.'e'))
@extends('documents.layout')

@section('title', 'Bulletins — '.$term->name.' '.$term->schoolYear->name)

@section('styles')
    @page { margin: 12mm 12mm; }
    body { font-size: 8.8pt; }
    .school-name { font-size: 12.5pt; }
    .logo { width: 56px; height: 56px; }
    .report { page-break-after: always; }
    .report:last-child { page-break-after: auto; }
    h1 { text-align: center; font-size: 13pt; margin: 10px 0 2px; letter-spacing: 1px; }
    .subtitle { text-align: center; font-size: 9.5pt; margin-bottom: 8px; }
    table.identity { width: 100%; border: 1px solid #555; border-collapse: collapse; margin-bottom: 8px; }
    table.identity td { padding: 3px 6px; }
    table.grades { width: 100%; border-collapse: collapse; }
    table.grades th { background: #eee; font-size: 7.8pt; }
    table.grades th, table.grades td { border: 1px solid #777; padding: 3px 4px; }
    table.grades td.num, table.grades th.num { text-align: center; }
    .teacher { font-size: 7pt; color: #555; }
    .comment { font-size: 7.6pt; font-style: italic; }
    table.summary { width: 100%; border-collapse: collapse; margin-top: 8px; }
    table.summary td { border: 1px solid #777; padding: 4px 6px; vertical-align: top; }
    .big { font-size: 13pt; font-weight: bold; }
    .distinction { font-weight: bold; color: #166534; }
    .signatures { width: 100%; margin-top: 14px; }
    .signatures td { width: 33%; text-align: center; font-size: 8pt; vertical-align: top; height: 55px; }
@endsection

@section('content')
    @foreach ($results as $result)
        @php($student = $result->enrollment->student)
        <div class="report">
            @include('documents.partials.school-header')

            <h1>BULLETIN DE NOTES</h1>
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

            <table class="grades">
                <thead>
                    <tr>
                        <th style="text-align: left;">Matière</th>
                        <th class="num">Coef.</th>
                        <th class="num">Moy./{{ $grading->max_score }}</th>
                        <th class="num">Points</th>
                        <th class="num">Rang</th>
                        <th class="num">Moy. classe</th>
                        <th class="num">Min – Max</th>
                        <th style="text-align: left; width: 30%;">Appréciation</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($result->subjectResults as $subject)
                        <tr>
                            <td>
                                <strong>{{ $subject->subject_name }}</strong>
                                @if ($subject->teacher_name)<br><span class="teacher">{{ $subject->teacher_name }}</span>@endif
                            </td>
                            <td class="num">{{ rtrim(rtrim(number_format((float) $subject->coefficient, 2, ',', ''), '0'), ',') }}</td>
                            <td class="num"><strong>{{ $num($subject->average) }}</strong></td>
                            <td class="num">{{ $subject->average === null ? '—' : $num((float) $subject->average * (float) $subject->coefficient) }}</td>
                            <td class="num">{{ $ordinal($subject->rank) }}</td>
                            <td class="num">{{ $num($subject->class_average) }}</td>
                            <td class="num">{{ $num($subject->class_lowest) }} – {{ $num($subject->class_highest) }}</td>
                            <td class="comment">{{ $subject->comment }}</td>
                        </tr>
                    @endforeach
                    <tr>
                        <td><strong>Totaux</strong></td>
                        <td class="num"><strong>{{ rtrim(rtrim(number_format((float) $result->total_coefficient, 2, ',', ''), '0'), ',') }}</strong></td>
                        <td></td>
                        <td class="num"><strong>{{ $num($result->total_points) }}</strong></td>
                        <td colspan="4"></td>
                    </tr>
                </tbody>
            </table>

            <table class="summary">
                <tr>
                    <td style="width: 34%;">
                        Moyenne du trimestre<br>
                        <span class="big">{{ $num($result->average) }} / {{ $grading->max_score }}</span><br>
                        Rang : <strong>{{ $result->rankLabel() ?? '—' }}</strong>
                        @if ($result->distinction)<br><span class="distinction">{{ $result->distinction }}</span>@endif
                    </td>
                    <td style="width: 26%;">
                        Moyenne de la classe : {{ $num($result->class_average) }}<br>
                        Plus forte moyenne : {{ $num($result->class_highest) }}<br>
                        Plus faible moyenne : {{ $num($result->class_lowest) }}<br>
                        <br>
                        Absences : {{ $result->absencesLabel() }}<br>
                        Retards : {{ $result->late_count }}
                    </td>
                    <td>
                        <strong>Appréciation du conseil de classe</strong><br>
                        <span class="comment">{{ $result->council_comment ?? '' }}</span>
                    </td>
                </tr>
                @if ($isLastTerm)
                    @php($annual = $annualResults->get($result->enrollment_id))
                    <tr>
                        <td colspan="2">
                            <strong>Récapitulatif annuel</strong><br>
                            @foreach ($term->schoolYear->terms as $yearTerm)
                                {{ $yearTerm->name }} : {{ $num($termAverages->get($result->enrollment_id)?->get($yearTerm->position)) }}<br>
                            @endforeach
                            Moyenne annuelle : <strong>{{ $num($annual?->average) }}</strong>
                            — rang {{ $ordinal($annual?->rank) }}{{ $annual?->rank_tied ? ' ex æquo' : '' }} / {{ $annual?->class_size ?? $result->class_size }}
                        </td>
                        <td>
                            <strong>Décision de fin d'année</strong><br>
                            {{ $result->enrollment->decision?->getLabel() ?? 'À prendre en conseil de classe' }}
                        </td>
                    </tr>
                @endif
            </table>

            <table class="signatures">
                <tr>
                    <td>Le professeur principal</td>
                    <td>Le chef d'établissement<br><br><br>{{ $settings?->director_name }}</td>
                    <td>Signature des parents</td>
                </tr>
            </table>
        </div>
    @endforeach
@endsection
