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
                <text ref="dashboard-hero-day-label" class="text-xs text-theme-on-surface-variant">{{ $dayScope === 1 ? 'Hier' : "Aujourd'hui" }}</text>
                <text class="text-base font-semibold text-theme-on-background">
                    {{ number_format($metrics['revenue_today'] ?? 0, 2, ',', ' ') }} € · {{ $metrics['orders_today'] ?? 0 }} commandes
                </text>
            </column>
        </row>

        @if ($widgets && $widgets['ca_forecast_percent'] !== null)
            <column class="w-full gap-1">
                <progress-bar ref="dashboard-forecast-progress" value="{{ min($widgets['ca_forecast_percent'], 100) / 100 }}" class="w-full" />
                <row class="w-full justify-between">
                    <text class="text-xs text-theme-on-surface-variant">{{ $widgets['ca_forecast_percent'] }}% de l'objectif atteint</text>
                    <text class="text-xs text-theme-on-surface-variant">objectif : {{ number_format($widgets['ca_forecast_predicted'], 0, ',', ' ') }} €</text>
                </row>
            </column>
        @elseif ($widgets)
            <text ref="dashboard-forecast-unavailable" class="text-xs text-theme-on-surface-variant">Prévision indisponible</text>
        @endif

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

        <row class="w-full gap-3">
            <column class="flex-1 gap-1 rounded-lg bg-theme-surface-variant p-4">
                <text class="text-xs text-theme-on-surface-variant">Panier moyen ({{ $dayScope === 1 ? 'hier' : 'aujourd\'hui' }})</text>
                <text class="text-xl font-bold text-theme-on-surface" content-transition="numeric">
                    {{ $widgets ? number_format($widgets['avg_cart'], 2, ',', ' ') : '0,00' }} €
                </text>
                @if ($widgets && $widgets['avg_cart_change'] !== null)
                    <text class="text-xs {{ $widgets['avg_cart_change'] >= 0 ? 'text-theme-primary' : 'text-theme-destructive' }}">
                        {{ $widgets['avg_cart_change'] >= 0 ? '+' : '' }}{{ number_format($widgets['avg_cart_change'], 1, ',', ' ') }}%
                    </text>
                @endif
            </column>
            <column class="flex-1 gap-1 rounded-lg bg-theme-surface-variant p-4">
                <text class="text-xs text-theme-on-surface-variant">Nouveaux clients</text>
                <text ref="dashboard-new-customers-value" class="text-xl font-bold text-theme-on-surface" content-transition="numeric">
                    {{ $widgets['new_customers'] ?? 0 }}
                </text>
            </column>
        </row>

        <row class="w-full">
            <column class="flex-1 gap-1 rounded-lg bg-theme-surface-variant p-4">
                <text class="text-xs text-theme-on-surface-variant">Tendance semaine</text>
                <text ref="dashboard-week-trend-value" class="text-xl font-bold {{ ($widgets['week_trend'] ?? 0) >= 0 ? 'text-theme-primary' : 'text-theme-destructive' }}">
                    {{ $widgets ? (($widgets['week_trend'] >= 0 ? '+' : '').number_format($widgets['week_trend'], 1, ',', ' ').'%') : '—' }}
                </text>
                <text class="text-xs text-theme-on-surface-variant">
                    {{ $widgets ? number_format($widgets['week_revenue'], 0, ',', ' ').' €' : '' }}
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

        <text class="text-base font-semibold text-theme-on-background">Top 5 produits</text>
        <column class="w-full gap-2">
            @forelse ($topProducts as $rank => $product)
                <row class="w-full items-center gap-3 rounded-lg bg-theme-surface-variant px-4 py-[10]">
                    <text class="w-[24] text-sm font-semibold text-theme-on-surface-variant">{{ $rank + 1 }}</text>
                    @if ($product['image_url'])
                        <image src="{{ $product['image_url'] }}" alt="{{ $product['name'] }}" class="h-[40] w-[40] rounded-md" />
                    @endif
                    <column class="flex-1 gap-0">
                        <text ref="dashboard-top-product-{{ $product['product_id'] }}-name" class="text-sm font-medium text-theme-on-surface">
                            {{ $product['name'] }}
                        </text>
                        @if ($product['category'])
                            <text class="text-xs text-theme-on-surface-variant">{{ $product['category'] }}</text>
                        @endif
                    </column>
                    <column class="items-end gap-0">
                        <text class="text-sm font-semibold text-theme-on-surface">{{ number_format($product['revenue'], 2, ',', ' ') }} €</text>
                        <text class="text-xs text-theme-on-surface-variant">{{ $product['quantity'] }} vendu(s)</text>
                    </column>
                </row>
            @empty
                <text ref="dashboard-top-products-empty" class="text-sm text-theme-on-surface-variant">Aucune vente aujourd'hui.</text>
            @endforelse
        </column>
    </column>
</refreshable>
