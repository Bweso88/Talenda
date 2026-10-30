<x-filament-widgets::widget>
    <div class="tl-card">
        <div class="tl-card-title">
            <span>Actions prioritaires</span>
            @if (count($items) > 0)
                <span class="tl-pill tl-tone-danger">{{ count($items) }}</span>
            @endif
        </div>

        @if ($items === [])
            <div class="tl-empty">Rien d'urgent pour le moment.</div>
        @else
            <div class="tl-list">
                @foreach ($items as $item)
                    <a href="{{ $item['url'] }}" class="tl-item tl-tone-{{ $item['tone'] }}">
                        <span class="tl-item-icon"><x-filament::icon :icon="$item['icon']" /></span>
                        <span class="tl-item-body">
                            <span class="tl-item-title">
                                <span>{{ $item['title'] }}</span>
                                <span class="tl-pill tl-tone-{{ $item['tone'] }}">{{ $item['badge'] }}</span>
                            </span>
                            <span class="tl-item-text">{{ $item['text'] }}</span>
                        </span>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</x-filament-widgets::widget>
