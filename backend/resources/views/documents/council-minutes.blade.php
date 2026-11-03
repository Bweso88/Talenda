@php($num = fn ($value) => $value === null ? '—' : number_format((float) $value, 2, ',', ''))
@php($ordinal = fn (?int $rank) => $rank === null ? '—' : ($rank === 1 ? '1er' : $rank.'e'))
@extends('documents.layout')

@section('title', 'Procès-verbal — '.$schoolClass->name.' — '.$term->name.' '.$term->schoolYear->name)

@section('styles')
    @page { margin: 10mm 10mm; }
    body { font-size: 8pt; }
    .school-name { font-size: 12pt; }
    .logo { width: 50px; height: 50px; }
    h1 { text-align: center; font-size: 12.5pt; margin: 8px 0 2px; letter-spacing: 1px; }
    .subtitle { text-align: center; font-size: 9pt; margin-bottom: 6px; }
    table.minutes { width: 100%; border-collapse: collapse; }
    table.minutes th { background: #eee; font-size: 6.6pt; }
    table.minutes td { font-size: 7.4pt; }
    table.minutes th, table.minutes td { border: 1px solid #777; padding: 2px 3px; }
    table.minutes td.num, table.minutes th.num { text-align: center; }
    .below { color: #b91c1c; }
    table.stats { width: 100%; border-collapse: collapse; margin-top: 8px; }
    table.stats td { border: 1px solid #777; padding: 4px 6px; }
    .signatures { width: 100%; margin-top: 14px; }
    .signatures td { width: 33%; text-align: center; font-size: 8pt; vertical-align: top; height: 50px; }
@endsection

@section('content')
    @include('documents.partials.school-header')

    <h1>PROCÈS-VERBAL DU CONSEIL DE CLASSE</h1>
    <div class="subtitle">
        Classe de <strong>{{ $schoolClass->name }}</strong> — {{ $term->name }} — Année scolaire {{ $term->schoolYear->name }}
        @if ($schoolClass->headTeacher) — Professeur principal : {{ $schoolClass->headTeacher->full_name }} @endif
    </div>

    <table class="minutes">
        <thead>
            <tr>
                <th class="num">Rang</th>
                <th style="text-align: left; width: 11%;">Élève</th>
                @foreach ($subjects as $subject)
                    <th class="num">{{ $subject }}</th>
                @endforeach
                <th class="num">Moy.</th>
                <th class="num">Mention</th>
                <th class="num">Absences</th>
                @if ($isLastTerm)
                    <th class="num">Moy. annuelle</th>
                    <th class="num">Rang annuel</th>
                    <th class="num">Décision</th>
                @endif
                <th style="text-align: left; width: 20%;">Appréciation du conseil</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($results as $result)
                @php($bySubject = $result->subjectResults->keyBy('subject_name'))
                @php($annual = $annualResults->get($result->enrollment_id))
                <tr>
                    <td class="num">{{ $ordinal($result->rank) }}{{ $result->rank_tied ? ' ex' : '' }}</td>
                    <td><strong>{{ $result->enrollment->student->full_name }}</strong></td>
                    @foreach ($subjects as $subject)
                        @php($average = $bySubject->get($subject)?->average)
                        <td class="num {{ $average !== null && (float) $average < (float) $grading->passing_average ? 'below' : '' }}">{{ $num($average) }}</td>
                    @endforeach
                    <td class="num"><strong>{{ $num($result->average) }}</strong></td>
                    <td class="num">{{ $result->distinction ?? '' }}</td>
                    <td class="num">{{ (new \App\Modules\SchoolLife\Support\AttendanceTotal($result->absence_minutes, $result->justified_absence_minutes, $result->late_count))->shortLabel() }}</td>
                    @if ($isLastTerm)
                        <td class="num"><strong>{{ $num($annual?->average) }}</strong></td>
                        <td class="num">{{ $ordinal($annual?->rank) }}</td>
                        <td class="num">{{ $result->enrollment->decision?->getLabel() ?? '—' }}</td>
                    @endif
                    <td>{{ $result->council_comment }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="stats">
        <tr>
            <td>Effectif : <strong>{{ $stats['size'] }}</strong></td>
            <td>Moyenne de la classe : <strong>{{ $num($stats['class_average']) }}</strong></td>
            <td>Plus forte : <strong>{{ $num($stats['highest']) }}</strong></td>
            <td>Plus faible : <strong>{{ $num($stats['lowest']) }}</strong></td>
            <td>Moyenne ≥ {{ $num($grading->passing_average) }} : <strong>{{ $stats['passing'] }}</strong> / {{ $stats['graded'] }}
                ({{ $stats['graded'] === 0 ? '—' : number_format($stats['passing'] / $stats['graded'] * 100, 0) }} %)</td>
        </tr>
    </table>

    <table class="signatures">
        <tr>
            <td>Le professeur principal</td>
            <td>Le secrétaire de séance</td>
            <td>{{ $settings?->city ? 'Fait à '.$settings->city : '' }}<br>Le chef d'établissement @if ($settings?->director_name)<br><strong>{{ $settings->director_name }}</strong>@endif</td>
        </tr>
    </table>
@endsection
