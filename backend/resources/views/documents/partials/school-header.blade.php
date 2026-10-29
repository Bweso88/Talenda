<table class="header">
    <tr>
        @if ($logo)
            <td style="width: 80px;"><img src="{{ $logo }}" class="logo" alt=""></td>
        @endif
        <td>
            <div class="school-name">{{ $tenant->name }}</div>
            @if ($settings?->motto)
                <div class="motto">{{ $settings->motto }}</div>
            @endif
            <div class="contact">
                {{ collect([$settings?->address, $settings?->city])->filter()->implode(' — ') }}
                @if ($settings?->phone) · Tél. {{ \App\Modules\Core\Support\PhoneNumber::format($settings->phone) }} @endif
                @if ($settings?->email) · {{ $settings->email }} @endif
            </div>
        </td>
        @if ($tenant->country_code === 'CG')
            <td class="country">
                RÉPUBLIQUE DU CONGO<br>
                Unité * Travail * Progrès
            </td>
        @endif
    </tr>
</table>
