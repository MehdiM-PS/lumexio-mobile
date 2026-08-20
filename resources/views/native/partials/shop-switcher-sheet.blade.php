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
    <column class="w-full h-full gap-2 p-4">
        <text class="text-base font-bold text-theme-on-surface">Vos boutiques</text>
        <native:scroll-view class="w-full flex-1">
            <column class="w-full gap-2">
                @foreach ($switcherShops as $shop)
                    {{-- Nested `<row>`, not a `flex-row` class on the pressable
                    itself — `<pressable>` has no dedicated native renderer, so
                    it falls back to a generic container whose layout direction
                    defaults to column unless `layout.flexDirection` is set;
                    `<row>`'s direction is hardcoded by its type regardless of
                    classes, which is the reliable way to get horizontal layout
                    here (see header.blade.php's shop-pill fix for the full
                    trace). --}}
                    <pressable
                        ref="shop-switcher-row-{{ $shop['id'] }}"
                        class="w-full rounded-lg {{ ($shop['id'] ?? null) === $activeShopId ? 'bg-theme-primary/10' : 'bg-theme-surface-variant' }} px-4 py-[12]"
                        @press="selectShop('{{ $shop['id'] }}')"
                    >
                        <row class="w-full items-center gap-3">
                            <native:image ref="shop-switcher-row-{{ $shop['id'] }}-logo" src="{{ $shopLogoUrl }}" :fit="1" alt="" class="h-[32] w-[32] rounded-[9]" />
                            <column class="flex-1 gap-0">
                                <text class="text-sm font-semibold text-theme-on-surface">{{ $shop['name'] ?? '' }}</text>
                                <text class="text-xs text-theme-on-surface-variant">{{ $shop['platform'] ?? '' }}</text>
                            </column>
                            {{--@if (($shop['revenue_delta_percent'] ?? null) !== null)
                                <text class="text-sm font-semibold {{ $shop['revenue_delta_percent'] >= 0 ? 'text-theme-success' : 'text-theme-destructive' }}">
                                    {{ $shop['revenue_delta_percent'] >= 0 ? '+' : '' }}{{ number_format($shop['revenue_delta_percent'], 1, ',', ' ') }}%
                                </text>
                            @endif--}}
                        </row>
                    </pressable>
                @endforeach
            </column>
        </native:scroll-view>
        {{-- The mock's "+ Ajouter une boutique" div (line ~464) has no
        onClick handler and renders with a dashed border — but per this
        slice's rule that every link must be functional, this is wired to
        HasHeaderChrome::openAddShop() rather than left inert (no native
        shop-creation flow exists to build, so it opens the marketing site
        in the system browser instead — same precedent as Login::openSignup()
        for the mock's own inert "Essai gratuit 30 jours" CTA). This UI
        framework's class grammar has no border-style utility (TailwindParser
        only emits border width/color/radius, never a dash pattern), so a
        solid border remains the closest available approximation of the
        mock's dashed one. --}}
        <pressable ref="shop-switcher-add-shop" class="w-full items-center rounded-lg border border-theme-outline p-[12] mt-[4]" @press="openAddShop">
            <text class="text-sm font-bold text-theme-primary">+ Ajouter une boutique</text>
        </pressable>
    </column>
</native:bottom-sheet>
