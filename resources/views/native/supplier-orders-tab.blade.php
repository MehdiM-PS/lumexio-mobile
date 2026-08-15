<refreshable @refresh="refresh">
    <column fill class="bg-theme-background gap-3 p-4">
        @if ($lastApiError)
            <row ref="so-error" class="w-full items-center gap-2 rounded-lg bg-theme-destructive/15 px-4 py-[10]">
                <text class="flex-1 text-sm text-theme-destructive">{{ $lastApiError }}</text>
            </row>
        @endif

        <row class="w-full gap-3">
            <column class="flex-1 gap-1 rounded-lg bg-theme-surface-variant p-3">
                <text class="text-xs text-theme-on-surface-variant">Total</text>
                <text ref="so-stat-total" class="text-lg font-bold text-theme-on-surface">{{ $stats['total'] }}</text>
            </column>
            <column class="flex-1 gap-1 rounded-lg bg-theme-surface-variant p-3">
                <text class="text-xs text-theme-on-surface-variant">En cours</text>
                <text ref="so-stat-pending" class="text-lg font-bold text-theme-on-surface">{{ $stats['pending'] }}</text>
            </column>
            <column class="flex-1 gap-1 rounded-lg bg-theme-surface-variant p-3">
                <text class="text-xs text-theme-on-surface-variant">Reçues (mois)</text>
                <text ref="so-stat-received" class="text-lg font-bold text-theme-on-surface">{{ $stats['received_month'] }}</text>
            </column>
        </row>

        <outlined-text-input ref="so-search" native:model.debounce.400ms="search" label="Rechercher" placeholder="Référence, nom ou fournisseur" />

        <select
            ref="so-status-filter"
            label="Statut"
            :options="$this->statusFilterOptions()"
            :value="$this->statusFilterValue()"
            @change="setStatusFilter"
        />

        <row class="w-full justify-between items-center">
            <chip ref="so-hide-completed" label="Masquer terminées" :selected="$hideCompleted" @change="toggleHideCompleted" />
        </row>

        <column class="w-full gap-2">
            @forelse ($orders as $order)
                <pressable
                    ref="so-{{ $order['id'] }}"
                    class="w-full items-start gap-1 rounded-lg border border-theme-outline bg-theme-surface px-4 py-[12]"
                    @press="select('{{ $order['id'] }}')"
                >
                    <row class="w-full justify-between">
                        <text ref="so-{{ $order['id'] }}-reference" class="text-sm font-semibold text-theme-on-surface">{{ $order['reference'] }}</text>
                        <row class="items-center rounded-full px-[8] py-[2] bg-[{{ $order['status_color'] }}]">
                            <text class="text-xs font-medium">{{ $order['status_label'] }}</text>
                        </row>
                    </row>
                    <text class="text-sm text-theme-on-surface-variant">{{ $order['supplier']['name'] ?? '—' }}</text>
                    <row class="w-full justify-between">
                        <text class="text-xs text-theme-on-surface-variant">
                            @if ($order['expected_delivery_date'])
                                Livraison prévue : {{ $order['expected_delivery_date'] }}
                            @endif
                        </text>
                        <text class="text-sm font-semibold text-theme-on-surface">{{ number_format($order['total_ht'], 2, ',', ' ') }} €</text>
                    </row>
                </pressable>
            @empty
                @if (! $lastApiError)
                    <text ref="so-empty" class="text-sm text-theme-on-surface-variant">Aucune commande fournisseur trouvée.</text>
                @endif
            @endforelse

            @if ($hasMorePages)
                <button ref="so-load-more" variant="secondary" @press="loadMore">Charger plus</button>
            @endif
        </column>
    </column>
</refreshable>
