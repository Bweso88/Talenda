@extends('documents.layout')

@section('title', 'Cartes scolaires')

@section('styles')
    @page { margin: 10mm; }
    table.grid { border-collapse: separate; border-spacing: 6mm 5mm; margin: -5mm -6mm; }
    td.card { width: 85.6mm; height: 54mm; border: 1px solid #999; border-radius: 3mm; padding: 0; vertical-align: top; overflow: hidden; }
    .band { background: {{ $settings?->primary_color ?? '#4f46e5' }}; color: #fff; padding: 2mm 3mm; font-size: 7.5pt; font-weight: bold; text-transform: uppercase; }
    .band small { display: block; font-weight: normal; text-transform: none; font-size: 6.5pt; }
    .inner { padding: 2mm 3mm; font-size: 8pt; line-height: 1.45; }
    .photo { width: 21mm; height: 26mm; border: 1px solid #bbb; text-align: center; font-size: 6pt; color: #999; }
    .photo img { width: 21mm; height: 26mm; }
    .name { font-weight: bold; font-size: 9pt; }
    .label { color: #555; }
    .year { position: absolute; }
@endsection

@section('content')
    <table class="grid">
        @foreach ($enrollments->chunk(2) as $pair)
            <tr>
                @foreach ($pair as $enrollment)
                    <td class="card">
                        <div class="band">
                            {{ $tenant->name }}
                            <small>Carte scolaire {{ $enrollment->schoolYear->name }}</small>
                        </div>
                        <table class="inner" style="width: 100%;">
                            <tr>
                                <td style="width: 23mm; vertical-align: top;">
                                    <div class="photo">
                                        @if ($photos[$enrollment->id])
                                            <img src="{{ $photos[$enrollment->id] }}" alt="">
                                        @else
                                            <br><br>Photo
                                        @endif
                                    </div>
                                </td>
                                <td style="vertical-align: top;">
                                    <div class="name">{{ $enrollment->student->full_name }}</div>
                                    <div><span class="label">Classe :</span> <strong>{{ $enrollment->schoolClass->name }}</strong></div>
                                    <div><span class="label">Matricule :</span> {{ $enrollment->student->registration_number }}</div>
                                    <div><span class="label">{{ ucfirst($enrollment->student->gender->bornLabel()) }} le :</span> {{ $enrollment->student->birth_date->format('d/m/Y') }}</div>
                                    @if ($settings?->phone)
                                        <div><span class="label">École :</span> {{ \App\Modules\Core\Support\PhoneNumber::format($settings->phone) }}</div>
                                    @endif
                                </td>
                            </tr>
                        </table>
                    </td>
                @endforeach
            </tr>
            @if ($loop->iteration % 4 === 0 && ! $loop->last)
                </table><div style="page-break-after: always;"></div><table class="grid">
            @endif
        @endforeach
    </table>
@endsection
