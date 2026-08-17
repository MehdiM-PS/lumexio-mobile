@php
    $state = \App\Models\LocalState::current();
    $alertCount = $state->unread_alert_count;
@endphp
<row class="w-full items-center justify-between px-2">
    <pressable class="flex-row items-center gap-2 rounded-full bg-theme-surface-variant px-4 py-[8]" @press="openShopSwitcher">
        <text class="text-sm font-semibold text-theme-on-surface">{{ $state->active_shop_name ?? 'Choisir une boutique' }}</text>
    </pressable>

    <row class="items-center gap-3">
        <pressable ref="header-account-button" class="h-[34] w-[34] items-center justify-center rounded-full bg-theme-surface-variant" @press="openAccountSheet">
            <native:icon name="person.fill" a11y-label="Mon compte" />
        </pressable>
        <pressable ref="header-alert-button" class="flex-row items-center gap-1 rounded-full bg-theme-surface-variant px-3 py-[6]" @press="goAlerts">
            <native:icon name="bell.fill" a11y-label="Alertes" />
            @if ($alertCount > 0)
                <badge ref="header-alert-badge" label="{{ $alertCount }}" variant="destructive" />
            @endif
        </pressable>
    </row>
</row>
