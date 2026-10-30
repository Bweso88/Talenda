@php($money = fn (int $amount) => \App\Modules\Finances\Support\Money::format($amount, $currency))
@extends('documents.layout')

@section('title', 'Reçu '.$payment->receipt_number)

@section('styles')
    @page { margin: 12mm 12mm; }
    body { font-size: 9.5pt; }
    .school-name { font-size: 12pt; }
    .logo { width: 52px; height: 52px; }
    h1 { text-align: center; font-size: 14pt; margin: 14px 0 2px; letter-spacing: 1px; }
    .number { text-align: center; font-size: 9pt; color: #444; margin-bottom: 12px; }
    table.info { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
    table.info td { padding: 3px 4px; }
    table.info td.label { color: #555; width: 38%; }
    .amount { font-size: 16pt; font-weight: bold; text-align: center; border: 2px solid #111; padding: 8px; margin: 10px 0; }
    table.schedule { width: 100%; border-collapse: collapse; font-size: 8.5pt; }
    table.schedule th, table.schedule td { border-bottom: 1px solid #ddd; padding: 3px 4px; text-align: left; }
    table.schedule td.num, table.schedule th.num { text-align: right; }
    .totals td { font-weight: bold; }
    .cancelled { color: #b91c1c; font-size: 18pt; font-weight: bold; text-align: center; border: 3px solid #b91c1c; padding: 6px; margin: 8px 0; }
    .signature { margin-top: 18px; width: 100%; }
    .signature td { width: 50%; vertical-align: top; font-size: 8.5pt; }
@endsection

@section('content')
    @include('documents.partials.school-header')

    <h1>REÇU DE PAIEMENT</h1>
    <div class="number">N° {{ $payment->receipt_number }} — {{ $payment->paid_on->format('d/m/Y') }}</div>

    @unless ($payment->isValid())
        <div class="cancelled">REÇU ANNULÉ le {{ $payment->reversed_at?->format('d/m/Y') }}</div>
    @endunless

    <table class="info">
        <tr><td class="label">Élève</td><td><strong>{{ $invoice->student->full_name }}</strong> (matricule {{ $invoice->student->registration_number }})</td></tr>
        <tr><td class="label">Classe</td><td>{{ $invoice->enrollment->schoolClass->name }} — année {{ $invoice->schoolYear->name }}</td></tr>
        <tr><td class="label">Versé par</td><td>{{ $payment->payer_name ?: '—' }}</td></tr>
        <tr><td class="label">Moyen de paiement</td><td>{{ $payment->method->getLabel() }}@if ($payment->reference) — réf. {{ $payment->reference }}@endif</td></tr>
        <tr><td class="label">Facture</td><td>{{ $invoice->number }}</td></tr>
    </table>

    <div class="amount">{{ $money($payment->amount) }}</div>

    <table class="schedule">
        <thead>
            <tr><th style="width: 26%;">Échéance</th><th>Date</th><th class="num">Montant</th><th class="num">Payé</th><th>Situation</th></tr>
        </thead>
        <tbody>
            @foreach ($statement->installments as $installment)
                <tr>
                    <td>{{ $installment->label }}</td>
                    <td>{{ $installment->dueOn->format('d/m/Y') }}</td>
                    <td class="num">{{ $money($installment->amount) }}</td>
                    <td class="num">{{ $money($installment->paid) }}</td>
                    <td>{{ $installment->statusLabel() }}</td>
                </tr>
            @endforeach
            <tr class="totals">
                <td colspan="2">Situation après ce paiement</td>
                <td class="num">{{ $money($statement->total) }}</td>
                <td class="num">{{ $money($statement->paid) }}</td>
                <td>Reste : {{ $money(max(0, $statement->balance())) }}</td>
            </tr>
        </tbody>
    </table>

    <table class="signature">
        <tr>
            <td>Encaissé par : {{ $payment->recorder->name }}<br>le {{ $payment->created_at->format('d/m/Y à H:i') }}</td>
            <td style="text-align: right;">Cachet et signature<br><br><br></td>
        </tr>
    </table>
@endsection
