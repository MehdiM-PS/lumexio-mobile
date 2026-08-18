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
        <native:icon ref="header-shop-chevron" name="chevron.down" size="10" class="text-theme-on-surface-variant" />
    </pressable>

    <row class="items-center gap-3">
        <pressable ref="header-account-button" class="h-[34] w-[34] items-center justify-center rounded-full bg-theme-surface border border-theme-outline" @press="openAccountSheet">
            <native:icon name="person" a11y-label="Mon compte" />
        </pressable>
        {{-- The badge is a SIBLING of the bell pressable, not its child.
        NodeStyleModifier's ClipRadiusModifier clips a node's whole composite
        content (background + children) to its own border-radius — with the
        button itself rounded-full (radius 9999), an absolutely-positioned
        badge nested *inside* it would be clipped away the instant it
        overflows the circle, which is the entire point of an overlapping
        corner badge. This outer row carries no rounded-* class, so nothing
        clips it, and the badge positions absolutely against IT instead of
        against the clipped circle — same structure as the mock's own CSS
        (badge as a sibling of a position:relative, non-clipping ancestor,
        not a child of the clipped circle). --}}
        <row class="relative">
            <pressable ref="header-alert-button" class="h-[34] w-[34] items-center justify-center rounded-full bg-theme-surface border border-theme-outline" @press="goAlerts">
                <native:icon name="bell" a11y-label="Alertes" />
            </pressable>
            @if ($alertCount > 0)
                <badge ref="header-alert-badge" class="absolute -top-[3] -right-[3]" label="{{ $alertCount }}" variant="destructive" />
            @endif
        </row>
    </row>
</row>
