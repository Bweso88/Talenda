<x-filament-widgets::widget>
    <div class="tl-card">
        <div class="tl-card-title"><span>Activité récente</span></div>

        @if ($events === [])
            <div class="tl-empty">Pas encore d'activité.</div>
        @else
            <div class="tl-list tl-list-2">
                @foreach ($events as $event)
                    <div class="tl-item tl-tone-{{ $event['tone'] }}">
                        <span class="tl-item-icon"><x-filament::icon :icon="$event['icon']" /></span>
                        <span class="tl-item-body">
                            <span class="tl-item-title">
                                <span>{{ $event['title'] }}</span>
                                <span class="tl-time">{{ $event['since'] }}</span>
                            </span>
                            <span class="tl-item-text">{{ $event['text'] }}</span>
                        </span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-filament-widgets::widget>
