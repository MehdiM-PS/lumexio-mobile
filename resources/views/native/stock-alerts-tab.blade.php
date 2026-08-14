<column fill class="bg-theme-background gap-3 p-4">
    @if ($lastApiError)
        <row ref="stock-alerts-error" class="w-full items-center gap-2 rounded-lg bg-theme-destructive/15 px-4 py-[10]">
            <text class="flex-1 text-sm text-theme-destructive">{{ $lastApiError }}</text>
        </row>
    @endif

    <row class="w-full gap-3">
        <column class="flex-1 gap-1 rounded-lg bg-theme-surface-variant p-3">
            <text class="text-xs text-theme-on-surface-variant">Total</text>
            <text ref="stat-total" class="text-lg font-bold text-theme-on-surface">{{ $stats['total'] }}</text>
        </column>
        <column class="flex-1 gap-1 rounded-lg bg-theme-surface-variant p-3">
            <text class="text-xs text-theme-on-surface-variant">Stock bas</text>
            <text ref="stat-low-stock" class="text-lg font-bold text-theme-on-surface">{{ $stats['low_stock'] }}</text>
        </column>
        <column class="flex-1 gap-1 rounded-lg bg-theme-surface-variant p-3">
            <text class="text-xs text-theme-on-surface-variant">Rupture</text>
            <text ref="stat-out-of-stock" class="text-lg font-bold text-theme-on-surface">{{ $stats['out_of_stock'] }}</text>
        </column>
    </row>

    <row class="w-full justify-between items-center">
        <chip label="Non lues uniquement" :selected="$unreadOnly === true" @press="toggleUnreadOnly" />
        <button ref="mark-all-read" variant="ghost" size="sm" @press="markAllAsRead">Tout marquer comme lu</button>
    </row>

    <refreshable @refresh="refresh">
        <column class="w-full gap-2">
            @forelse ($alerts as $alert)
                <row class="w-full items-start justify-between gap-2 rounded-lg border border-theme-outline bg-theme-surface px-4 py-[12]">
                    <column class="flex-1 gap-1">
                        <row class="items-center gap-2">
                            <badge
                                label="{{ $alert['severity'] === 'critical' ? 'Critique' : ($alert['severity'] === 'warning' ? 'Attention' : 'Info') }}"
                                variant="{{ $alert['severity'] === 'critical' ? 'destructive' : ($alert['severity'] === 'warning' ? 'accent' : 'primary') }}"
                            />
                            <text ref="alert-title-{{ $alert['id'] }}" class="text-sm font-semibold text-theme-on-surface">{{ $alert['title'] }}</text>
                        </row>
                        <text class="text-sm text-theme-on-surface-variant">{{ $alert['message'] }}</text>
                    </column>
                    @if (! $alert['is_read'])
                        <button ref="mark-read-{{ $alert['id'] }}" variant="ghost" size="sm" @press="markAsRead({{ $alert['id'] }})">Lu</button>
                    @endif
                </row>
            @empty
                @if (! $lastApiError)
                    <text class="text-sm text-theme-on-surface-variant">Aucune alerte.</text>
                @endif
            @endforelse
        </column>
    </refreshable>
</column>
