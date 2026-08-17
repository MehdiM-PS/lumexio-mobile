@php
    // Read once, not once per row: LocalState::current() is a firstOrCreate()
    // query. $this->currentShopId() isn't usable here — @include'd partials
    // render through a static closure with no $this binding.
    $activeShopId = \App\Models\LocalState::current()->shop_id;
@endphp
<native:bottom-sheet ref="shop-switcher-sheet" :visible="$shopSheetOpen" detents="medium,large" @dismiss="closeShopSwitcher">
    <column class="w-full gap-2 p-4">
        <text class="text-base font-bold text-theme-on-surface">Vos boutiques</text>
        <column class="w-full gap-2">
            @foreach ($switcherShops as $shop)
                <pressable
                    ref="shop-switcher-row-{{ $shop['id'] }}"
                    class="w-full flex-row items-center justify-between gap-3 rounded-lg {{ ($shop['id'] ?? null) === $activeShopId ? 'bg-theme-primary/10' : 'bg-theme-surface-variant' }} px-4 py-[12]"
                    @press="selectShop('{{ $shop['id'] }}')"
                >
                    <column class="gap-0">
                        <text class="text-sm font-semibold text-theme-on-surface">{{ $shop['name'] ?? '' }}</text>
                        <text class="text-xs text-theme-on-surface-variant">{{ $shop['platform'] ?? '' }}</text>
                    </column>
                    @if (($shop['revenue_delta_percent'] ?? null) !== null)
                        <text class="text-sm font-semibold {{ $shop['revenue_delta_percent'] >= 0 ? 'text-theme-success' : 'text-theme-destructive' }}">
                            {{ $shop['revenue_delta_percent'] >= 0 ? '+' : '' }}{{ number_format($shop['revenue_delta_percent'], 1, ',', ' ') }}%
                        </text>
                    @endif
                </pressable>
            @endforeach
        </column>
    </column>
</native:bottom-sheet>
