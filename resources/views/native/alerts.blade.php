<refreshable @refresh="refresh">
    <column fill class="bg-theme-background gap-3 p-4">
        @if ($lastApiError)
            <row ref="alerts-error" class="w-full items-center gap-2 rounded-lg bg-theme-destructive/15 px-4 py-[10]">
                <text class="flex-1 text-sm text-theme-destructive">{{ $lastApiError }}</text>
            </row>
        @endif

        <row class="w-full gap-2">
            <column class="flex-1 gap-1 rounded-lg bg-theme-surface-variant p-3">
                <text class="text-xs text-theme-on-surface-variant">Total</text>
                <text ref="alerts-stat-total" class="text-lg font-bold text-theme-on-surface">{{ $stats['total'] }}</text>
            </column>
            <column class="flex-1 gap-1 rounded-lg bg-theme-surface-variant p-3">
                <text class="text-xs text-theme-on-surface-variant">Non lues</text>
                <text ref="alerts-stat-unread" class="text-lg font-bold text-theme-on-surface">{{ $stats['unread'] }}</text>
            </column>
            <column class="flex-1 gap-1 rounded-lg bg-theme-surface-variant p-3">
                <text class="text-xs text-theme-on-surface-variant">Critiques</text>
                <text ref="alerts-stat-critical" class="text-lg font-bold text-theme-destructive">{{ $stats['critical_unread'] }}</text>
            </column>
            <column class="flex-1 gap-1 rounded-lg bg-theme-surface-variant p-3">
                <text class="text-xs text-theme-on-surface-variant">Aujourd'hui</text>
                <text ref="alerts-stat-today" class="text-lg font-bold text-theme-on-surface">{{ $stats['today'] }}</text>
            </column>
        </row>

        <row class="w-full gap-2">
            <chip ref="alerts-severity-all" label="Toutes" :selected="$severityFilter === null" @change="setSeverityFilter(null)" />
            <chip ref="alerts-severity-critical" label="Critique" :selected="$severityFilter === 'critical'" @change="setSeverityFilter('critical')" />
            <chip ref="alerts-severity-warning" label="Attention" :selected="$severityFilter === 'warning'" @change="setSeverityFilter('warning')" />
            <chip ref="alerts-severity-info" label="Info" :selected="$severityFilter === 'info'" @change="setSeverityFilter('info')" />
        </row>

        <select
            ref="alerts-type-filter"
            label="Type d'alerte"
            :options="$this->typeFilterOptions()"
            :value="$this->typeFilterValue()"
            @change="setTypeFilter"
        />

        <row class="w-full justify-between items-center">
            <chip ref="alerts-unread-only" label="Non lues uniquement" :selected="$unreadOnly === true" @change="toggleUnreadOnly" />
        </row>

        <row class="w-full gap-2">
            <button ref="alerts-mark-all-read" variant="ghost" size="sm" @press="markAllAsRead">Tout marquer comme lu</button>
            <button ref="alerts-delete-read" variant="ghost" size="sm" @press="confirmDeleteRead">Supprimer les alertes lues</button>
        </row>

        <column class="w-full gap-2">
            @forelse ($alerts as $alert)
                <row class="w-full items-start justify-between gap-2 rounded-lg border border-theme-outline bg-theme-surface px-4 py-[12]">
                    <column class="flex-1 gap-1">
                        <row class="items-center gap-2">
                            <badge
                                label="{{ $alert['severity'] === 'critical' ? 'Critique' : ($alert['severity'] === 'warning' ? 'Attention' : 'Info') }}"
                                variant="{{ $alert['severity'] === 'critical' ? 'destructive' : ($alert['severity'] === 'warning' ? 'accent' : 'primary') }}"
                            />
                            <text class="text-xs text-theme-on-surface-variant">{{ $alert['type_label'] ?? '' }}</text>
                        </row>
                        <text ref="alert-{{ $alert['id'] }}-title" class="text-sm font-semibold text-theme-on-surface">{{ $alert['title'] }}</text>
                        <text class="text-sm text-theme-on-surface-variant">{{ $alert['message'] }}</text>
                        <text class="text-xs text-theme-on-surface-variant">
                            {{ \Carbon\Carbon::parse($alert['created_at'])->format('d/m/Y H:i') }}
                            @if ($alert['is_sent'] ?? false)
                                · notifié
                            @endif
                        </text>
                    </column>
                    @if (! $alert['is_read'])
                        <button ref="alert-{{ $alert['id'] }}-mark-read" variant="ghost" size="sm" @press="markAsRead({{ $alert['id'] }})">Lu</button>
                    @endif
                </row>
            @empty
                @if (! $lastApiError)
                    <text ref="alerts-empty" class="text-sm text-theme-on-surface-variant">Aucune alerte.</text>
                @endif
            @endforelse
        </column>
    </column>
</refreshable>
