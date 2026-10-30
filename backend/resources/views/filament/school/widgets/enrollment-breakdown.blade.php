<x-filament-widgets::widget>
    <div class="tl-card">
        <div class="tl-card-title">
            <span>Répartition des {{ number_format($total, 0, ',', ' ') }} élèves</span>
            <span class="tl-muted" style="font-weight: 500; font-size: 0.875rem;">par cycle</span>
        </div>

        @if ($cycles === [])
            <div class="tl-empty">Aucun élève inscrit pour l'instant.</div>
        @else
            <div class="tl-bar">
                @foreach ($cycles as $cycle)
                    <span style="width: {{ $cycle['percent'] }}%; background: {{ $cycle['color'] }};" title="{{ $cycle['name'] }}"></span>
                @endforeach
            </div>
            <div class="tl-legend">
                @foreach ($cycles as $cycle)
                    <span><i style="background: {{ $cycle['color'] }};"></i>{{ $cycle['name'] }} : <strong>{{ $cycle['count'] }}</strong> ({{ $cycle['percent'] }} %)</span>
                @endforeach
            </div>
        @endif
    </div>
</x-filament-widgets::widget>
