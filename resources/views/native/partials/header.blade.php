@php
    $state = \App\Models\LocalState::current();
    $alertCount = $state->unread_alert_count;
    // Same config('lumexio.asset_url') + native:image `:fit="1"` pattern
    // Task 5 introduced for Login::logoUrl() — this partial has no bound
    // $this (rendered directly by TabsLayout::navBar(), not @include'd from
    // within a screen component), so the URL is built inline rather than
    // via a screen method.
    $shopLogoUrl = rtrim(config('lumexio.asset_url'), '/').'/images/lumexio-logo.png';
@endphp
<row class="w-full items-center justify-between px-2">
    <pressable class="flex-row items-center gap-[6] rounded-full bg-theme-surface border border-theme-outline pl-2 pr-3 py-[7]" @press="openShopSwitcher">
        <native:image ref="header-shop-logo" src="{{ $shopLogoUrl }}" :fit="1" alt="" class="h-[20] w-[20] rounded-[6]" />
        <text class="text-sm font-semibold text-theme-on-surface">{{ $state->active_shop_name ?? 'Choisir une boutique' }}</text>
        <native:icon name="chevron.down" size="10" class="text-theme-on-surface-variant" />
    </pressable>

    <row class="items-center gap-3">
        <pressable ref="header-account-button" class="relative h-[34] w-[34] items-center justify-center rounded-full bg-theme-surface border border-theme-outline" @press="openAccountSheet">
            <native:icon name="person" a11y-label="Mon compte" />
        </pressable>
        <pressable ref="header-alert-button" class="relative h-[34] w-[34] items-center justify-center rounded-full bg-theme-surface border border-theme-outline" @press="goAlerts">
            <native:icon name="bell" a11y-label="Alertes" />
            @if ($alertCount > 0)
                <badge ref="header-alert-badge" class="absolute -top-[3] -right-[3]" label="{{ $alertCount }}" variant="destructive" />
            @endif
        </pressable>
    </row>
</row>
