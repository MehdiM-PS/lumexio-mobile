@php
    // Read once, not once per row: LocalState::current() is a firstOrCreate()
    // query. $this->currentShopId() isn't usable here — @include'd partials
    // render through a static closure with no $this binding.
    $activeShopId = \App\Models\LocalState::current()->shop_id;
    // Same config('lumexio.asset_url') + native:image `:fit="1"` pattern as
    // header.blade.php and Login::logoUrl() (Task 5) — every shop currently
    // shares the one Lumexio mark, same as the mock's shop rows (line ~455).
    $shopLogoUrl = rtrim(config('lumexio.asset_url'), '/').'/images/lumexio-logo.png';
@endphp
<native:bottom-sheet ref="shop-switcher-sheet" :visible="$shopSheetOpen" detents="medium,large" @dismiss="closeShopSwitcher">
    <column class="w-full gap-2 p-4">
        <text class="text-base font-bold text-theme-on-surface">Vos boutiques</text>
        <column class="w-full gap-2">
            @foreach ($switcherShops as $shop)
                <pressable
                    ref="shop-switcher-row-{{ $shop['id'] }}"
                    class="w-full flex-row items-center gap-3 rounded-lg {{ ($shop['id'] ?? null) === $activeShopId ? 'bg-theme-primary/10' : 'bg-theme-surface-variant' }} px-4 py-[12]"
                    @press="selectShop('{{ $shop['id'] }}')"
                >
                    <native:image ref="shop-switcher-row-{{ $shop['id'] }}-logo" src="{{ $shopLogoUrl }}" :fit="1" alt="" class="h-[32] w-[32] rounded-[9]" />
                    <column class="flex-1 gap-0">
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
        {{-- Presentational only, matching the mock: the "+ Ajouter une
        boutique" div at line ~464 has no onClick handler in the mockup
        either — it's a static affordance, not a wired action, so no
        backend "add a shop" flow needs to exist for this to be faithful.
        The mock renders it with a dashed border; this UI framework's class
        grammar has no border-style utility (TailwindParser only emits
        border width/color/radius, never a dash pattern), so a solid
        border is the closest available approximation. --}}
        <column ref="shop-switcher-add-shop" class="w-full items-center rounded-lg border border-theme-outline p-[12] mt-[4]">
            <text class="text-sm font-bold text-theme-primary">+ Ajouter une boutique</text>
        </column>
    </column>
</native:bottom-sheet>
