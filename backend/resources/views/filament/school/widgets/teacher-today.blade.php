<x-filament-widgets::widget>
    <div class="tl-card">
        <div class="tl-card-title">
            <span>Mes classes aujourd'hui</span>
            @if ($gradesUrl)
                <a href="{{ $gradesUrl }}" class="tl-pill">Mes évaluations</a>
            @endif
        </div>

        @if ($classes === [])
            <div class="tl-empty">Aucune classe ne vous est encore affectée.</div>
        @else
            <div class="tl-list">
                @foreach ($classes as $class)
                    <a href="{{ $attendanceUrl }}" class="tl-item">
                        <span class="tl-item-icon"><x-filament::icon icon="heroicon-o-user-group" /></span>
                        <span class="tl-item-body">
                            <span class="tl-item-title"><span>{{ $class['name'] }}</span></span>
                            <span class="tl-legend" style="margin-top: 0.35rem;">
                                @foreach ($class['slots'] as $slot)
                                    <span class="tl-pill {{ $slot['done'] ? 'tl-tone-success' : 'tl-tone-warning' }}">
                                        {{ $slot['name'] }} : {{ $slot['done'] ? 'appel fait' : 'appel à faire' }}
                                    </span>
                                @endforeach
                            </span>
                        </span>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</x-filament-widgets::widget>
