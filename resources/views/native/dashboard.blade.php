<refreshable @refresh="refresh">
    <column fill class="bg-theme-background px-4 py-4 gap-4">
        {{-- native:model comes before @change so its expanded `_change` attr
        (silent __syncProperty write) is overwritten by @change's `_change`
        (setDayScope callback) rather than the other way around — PHP array
        literals resolve duplicate keys last-wins. native:model still drives
        the read side (:value="$dayScope"); @change carries the write side
        since setDayScope() has a refresh() side effect, unlike tab-row's
        bare native:model in stock.blade.php. --}}
        <button-group ref="dashboard-day-scope" native:model="dayScope" @change="setDayScope" :options="['Aujourd\'hui', 'Hier']" class="w-full" />

        @if ($syncStatus === 'failed')
            <row ref="dashboard-sync-failed" class="w-full items-center gap-2 rounded-lg bg-theme-destructive/15 px-4 py-[10]">
                <text class="flex-1 text-sm text-theme-destructive">
                    {{ filled($syncError) ? $syncError : 'La dernière synchronisation a échoué. Les données peuvent être obsolètes.' }}
                </text>
            </row>
        @elseif ($syncStatus === 'syncing')
            <row ref="dashboard-sync-syncing" class="w-full items-center gap-2 rounded-lg bg-theme-surface-variant px-4 py-[10]">
                <text class="flex-1 text-sm text-theme-on-surface-variant">Synchronisation en cours…</text>
            </row>
        @endif

        @if ($lastApiError)
            <row ref="dashboard-error" class="w-full items-center gap-2 rounded-lg bg-theme-destructive/15 px-4 py-[10]">
                <text class="flex-1 text-sm text-theme-destructive">{{ $lastApiError }}</text>
            </row>
        @endif

        <row class="w-full justify-between">
            <column class="gap-1">
                <text class="text-xs text-theme-on-surface-variant">Aujourd'hui</text>
                <text class="text-base font-semibold text-theme-on-background">
                    {{ number_format($metrics['revenue_today'] ?? 0, 2, ',', ' ') }} € · {{ $metrics['orders_today'] ?? 0 }} commandes
                </text>
            </column>
        </row>

        <row class="w-full gap-3">
            <column class="flex-1 gap-1 rounded-lg bg-theme-surface-variant p-4">
                <text class="text-xs text-theme-on-surface-variant">CA période</text>
                <text class="text-xl font-bold text-theme-on-surface" content-transition="numeric">
                    {{ $this->formattedRevenuePeriod() }}
                </text>
            </column>
            <column class="flex-1 gap-1 rounded-lg bg-theme-surface-variant p-4">
                <text class="text-xs text-theme-on-surface-variant">Commandes</text>
                <text class="text-xl font-bold text-theme-on-surface" content-transition="numeric">
                    {{ $metrics['orders_period'] ?? 0 }}
                </text>
            </column>
        </row>

        <row class="w-full gap-3">
            <column class="flex-1 gap-1 rounded-lg bg-theme-surface-variant p-4">
                <text class="text-xs text-theme-on-surface-variant">Panier moyen</text>
                <text class="text-xl font-bold text-theme-on-surface">
                    {{ number_format($metrics['avg_order_value'] ?? 0, 2, ',', ' ') }} €
                </text>
            </column>
            <column class="flex-1 gap-1 rounded-lg bg-theme-surface-variant p-4">
                <text class="text-xs text-theme-on-surface-variant">Clients</text>
                <text class="text-xl font-bold text-theme-on-surface" content-transition="numeric">
                    {{ $metrics['customers_count'] ?? 0 }}
                </text>
            </column>
        </row>

        <text class="text-xs text-theme-on-surface-variant">
            {{ $metrics['products_count'] ?? 0 }} produits actifs · {{ $metrics['low_stock_count'] ?? 0 }} en stock bas
        </text>

        @if (count($chartRevenue) > 0)
            <canvas class="w-full h-[80]">
                <row class="w-full h-full items-end justify-between gap-2">
                    @foreach ($chartRevenue as $value)
                        <rect class="flex-1 rounded-sm bg-theme-primary" height="{{ $this->barHeight($value) }}" />
                    @endforeach
                </row>
            </canvas>
        @endif

        <text class="text-base font-semibold text-theme-on-background">Commandes récentes</text>
        <column class="w-full gap-2">
            @forelse ($recentOrders as $order)
                <row class="w-full justify-between rounded-lg bg-theme-surface-variant px-4 py-[10]">
                    <column class="gap-0">
                        <text class="text-sm font-medium text-theme-on-surface">
                            {{ $order['customer']['firstname'] ?? '' }} {{ $order['customer']['lastname'] ?? '' }}
                        </text>
                        <text class="text-xs text-theme-on-surface-variant">{{ $order['reference'] ?? '' }}</text>
                    </column>
                    <text class="text-sm font-semibold text-theme-on-surface">
                        {{ number_format($order['total_paid'] ?? 0, 2, ',', ' ') }} €
                    </text>
                </row>
            @empty
                <text class="text-sm text-theme-on-surface-variant">Aucune commande récente.</text>
            @endforelse
        </column>

        <text class="text-base font-semibold text-theme-on-background">Stock bas</text>
        <column class="w-full gap-2">
            @forelse ($lowStockProducts as $product)
                <row class="w-full justify-between rounded-lg bg-theme-surface-variant px-4 py-[10]">
                    <text class="text-sm font-medium text-theme-on-surface">{{ $product['name'] ?? '' }}</text>
                    <text class="text-sm text-theme-destructive">
                        {{ $product['quantity'] ?? 0 }} / {{ $product['low_stock_threshold'] ?? 0 }}
                    </text>
                </row>
            @empty
                <text class="text-sm text-theme-on-surface-variant">Aucun produit en stock bas.</text>
            @endforelse
        </column>
    </column>
</refreshable>
