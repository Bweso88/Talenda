<x-filament-widgets::widget>
    <div class="tl-banner">
        <span class="tl-banner-chip">{{ $period }}</span>
        <h2>{{ $greeting }}</h2>
        <p>{{ $school }}</p>

        @if ($actions !== [])
            <div class="tl-banner-actions">
                @foreach ($actions as $action)
                    <a href="{{ $action['url'] }}" @class(['tl-btn', 'tl-btn-primary' => $action['primary']])>
                        <x-filament::icon :icon="$action['icon']" />
                        {{ $action['label'] }}
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</x-filament-widgets::widget>
