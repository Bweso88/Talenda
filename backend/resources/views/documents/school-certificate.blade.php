@extends('documents.layout')

@section('title', 'Certificat de scolarité — '.$student->full_name)

@section('styles')
    h1 { text-align: center; font-size: 18pt; letter-spacing: 2px; margin: 40px 0 6px; }
    .number { text-align: center; font-size: 9pt; color: #555; margin-bottom: 40px; }
    .body { line-height: 2; text-align: justify; font-size: 12pt; }
    .signature { margin-top: 60px; width: 100%; }
    .signature td { width: 50%; vertical-align: top; }
    .footnote { position: fixed; bottom: 0; font-size: 8pt; color: #666; }
@endsection

@section('content')
    @include('documents.partials.school-header')

    <h1>CERTIFICAT DE SCOLARITÉ</h1>
    <div class="number">N° {{ $number }}</div>

    <div class="body">
        Je soussigné(e), <strong>{{ $settings?->director_name ?: '....................................' }}</strong>,
        chef d'établissement du <strong>{{ $tenant->name }}</strong>, certifie que l'élève&nbsp;:
        <br>
        <strong>{{ $student->full_name }}</strong>,
        {{ $student->gender->bornLabel() }} le <strong>{{ $student->birth_date->format('d/m/Y') }}</strong>
        @if ($student->birth_place) à <strong>{{ $student->birth_place }}</strong>@endif,
        matricule <strong>{{ $student->registration_number }}</strong>,
        <br>
        est régulièrement {{ $student->gender->enrolledLabel() }} dans notre établissement en classe de
        <strong>{{ $enrollment->schoolClass->name }}</strong>
        pour l'année scolaire <strong>{{ $enrollment->schoolYear->name }}</strong>.
        <br>
        En foi de quoi, le présent certificat lui est délivré pour servir et valoir ce que de droit.
    </div>

    <table class="signature">
        <tr>
            <td></td>
            <td>
                Fait à {{ $settings?->city ?: '....................' }}, le {{ $issuedOn->translatedFormat('j F Y') }}<br><br>
                Le chef d'établissement<br><br><br><br>
                {{ $settings?->director_name }}
            </td>
        </tr>
    </table>

    <div class="footnote">Document délivré avec Talenda — N° {{ $number }}</div>
@endsection
