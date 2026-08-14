<column fill class="safe-area bg-theme-background px-6 py-6 gap-3">
    <text class="text-xl font-bold text-theme-on-background" font="InstrumentSerif-Regular">
        Choisis une boutique
    </text>

    @if ($lastApiError)
        <text ref="shops-error" class="text-sm text-theme-destructive">{{ $lastApiError }}</text>
    @endif

    @if (count($shops) === 0 && ! $lastApiError)
        <column ref="shops-empty" class="w-full items-start gap-3 rounded-lg border border-theme-outline bg-theme-surface px-4 py-[14]">
            <text class="text-sm text-theme-on-surface-variant">Aucune boutique accessible avec ce compte.</text>
            <button ref="shops-retry" variant="primary" @press="retry">Réessayer</button>
        </column>
    @endif

    @foreach ($shops as $shop)
        <pressable
            ref="shop-{{ $shop['id'] }}"
            class="w-full items-start gap-1 rounded-lg border border-theme-outline bg-theme-surface px-4 py-[14]"
            @press="select('{{ $shop['id'] }}')"
        >
            <text class="text-base font-semibold text-theme-on-surface">{{ $shop['name'] }}</text>
            <text class="text-sm text-theme-on-surface-variant">{{ $shop['domain'] }}</text>
        </pressable>
    @endforeach
</column>
