<refreshable @refresh="refresh">
    <column fill class="bg-theme-background gap-3 p-4">
        @if ($lastApiError)
            <row class="w-full items-center gap-2 rounded-lg bg-theme-destructive/15 px-4 py-[10]">
                <text class="flex-1 text-sm text-theme-destructive">{{ $lastApiError }}</text>
            </row>
        @endif

        <outlined-text-input
            ref="stock-search"
            native:model.debounce.400ms="search"
            label="Rechercher"
            placeholder="Nom ou référence"
        />

        <row class="w-full gap-2">
            <chip ref="chip-status-all" label="Tous" :selected="$statusFilter === 'all'" @change="setStatusFilter('all')" />
            <chip ref="chip-status-low" label="Stock bas" :selected="$statusFilter === 'low'" @change="setStatusFilter('low')" />
            <chip ref="chip-status-out" label="Rupture" :selected="$statusFilter === 'out'" @change="setStatusFilter('out')" />
        </row>

        <column class="w-full gap-2">
            @forelse ($items as $item)
                <pressable
                    ref="stock-item-{{ $item['type'] }}-{{ $item['id'] }}"
                    class="w-full items-start gap-1 rounded-lg border border-theme-outline bg-theme-surface px-4 py-[12]"
                    @press="select('{{ $item['type'] }}', {{ $item['id'] }})"
                >
                    <text class="text-base font-semibold text-theme-on-surface">{{ $item['name'] }}</text>
                    <row class="w-full justify-between">
                        <text class="text-sm text-theme-on-surface-variant">{{ $item['reference'] }}</text>
                        <text class="text-sm font-medium {{ $item['quantity'] <= 0 ? 'text-theme-destructive' : ($item['quantity'] <= $item['low_stock_threshold'] ? 'text-theme-accent' : 'text-theme-on-surface') }}">
                            {{ $item['quantity'] }} / {{ $item['low_stock_threshold'] }}
                        </text>
                    </row>
                </pressable>
            @empty
                @if (! $lastApiError)
                    <text class="text-sm text-theme-on-surface-variant">Aucun produit trouvé.</text>
                @endif
            @endforelse
        </column>
    </column>
</refreshable>
