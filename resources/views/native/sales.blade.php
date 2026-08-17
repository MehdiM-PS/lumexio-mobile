<refreshable @refresh="refresh">
    <column fill class="bg-theme-background gap-3 p-4">
        @if ($lastApiError)
            <row ref="sales-error" class="w-full items-center gap-2 rounded-lg bg-theme-destructive/15 px-4 py-[10]">
                <text class="flex-1 text-sm text-theme-destructive">{{ $lastApiError }}</text>
            </row>
        @endif

        <text class="text-lg font-bold text-theme-on-background">CA &amp; Ventes</text>
        <text class="text-xs text-theme-on-surface-variant">{{ $this->periodLabel() }}</text>

        <scroll-view horizontal class="gap-2">
            @foreach ($this->periodOptions() as $key => $label)
                <chip ref="sales-period-{{ $key }}" label="{{ $label }}" :selected="$salesPeriod === '{{ $key }}'" @change="setSalesPeriod('{{ $key }}')" />
            @endforeach
        </scroll-view>

        <row class="w-full justify-between items-center">
            <text class="text-xs text-theme-on-surface-variant">Comparer</text>
            <toggle ref="sales-compare-toggle" a11y-label="Comparer" native:model="compareEnabled" />
        </row>

        <row class="w-full gap-3">
            <column class="flex-1 gap-1 rounded-lg bg-theme-surface border border-theme-outline p-[14]">
                <text class="text-xs text-theme-on-surface-variant">Chiffre d'affaires</text>
                <text ref="sales-metric-revenue" class="text-xl font-bold text-theme-on-background">{{ number_format($metrics['current']['revenue'], 2, ',', ' ') }} €</text>
                <text class="text-xs font-bold {{ $metrics['changes']['revenue'] >= 0 ? 'text-theme-primary' : 'text-theme-destructive' }}">{{ $metrics['changes']['revenue'] >= 0 ? '+' : '' }}{{ number_format($metrics['changes']['revenue'], 0, ',', ' ') }}%</text>
            </column>
            <column class="flex-1 gap-1 rounded-lg bg-theme-surface border border-theme-outline p-[14]">
                <text class="text-xs text-theme-on-surface-variant">Commandes</text>
                <text ref="sales-metric-orders" class="text-xl font-bold text-theme-on-background">{{ number_format($metrics['current']['orders'], 0, ',', ' ') }}</text>
                <text class="text-xs font-bold {{ $metrics['changes']['orders'] >= 0 ? 'text-theme-primary' : 'text-theme-destructive' }}">{{ $metrics['changes']['orders'] >= 0 ? '+' : '' }}{{ number_format($metrics['changes']['orders'], 0, ',', ' ') }}%</text>
            </column>
        </row>
        <row class="w-full gap-3">
            <column class="flex-1 gap-1 rounded-lg bg-theme-surface border border-theme-outline p-[14]">
                <text class="text-xs text-theme-on-surface-variant">Panier moyen</text>
                <text ref="sales-metric-avg-order" class="text-xl font-bold text-theme-on-background">{{ number_format($metrics['current']['avg_order'], 2, ',', ' ') }} €</text>
                <text class="text-xs font-bold {{ $metrics['changes']['avg_order'] >= 0 ? 'text-theme-primary' : 'text-theme-destructive' }}">{{ $metrics['changes']['avg_order'] >= 0 ? '+' : '' }}{{ number_format($metrics['changes']['avg_order'], 0, ',', ' ') }}%</text>
            </column>
            <column class="flex-1 gap-1 rounded-lg bg-theme-surface border border-theme-outline p-[14]">
                <text class="text-xs text-theme-on-surface-variant">Nouveaux clients</text>
                <text ref="sales-metric-new-customers" class="text-xl font-bold text-theme-on-background">{{ number_format($metrics['current']['new_customers'], 0, ',', ' ') }}</text>
                <text class="text-xs font-bold {{ $metrics['changes']['new_customers'] >= 0 ? 'text-theme-primary' : 'text-theme-destructive' }}">{{ $metrics['changes']['new_customers'] >= 0 ? '+' : '' }}{{ number_format($metrics['changes']['new_customers'], 0, ',', ' ') }}%</text>
            </column>
        </row>

        @if ($compareEnabled)
            <text class="text-base font-semibold text-theme-on-background mt-[10]">Actuelle vs période -1</text>
            <column class="w-full rounded-lg border border-theme-outline bg-theme-surface">
                <row class="w-full justify-between px-4 py-[10] bg-theme-surface-variant">
                    <text class="text-xs font-bold text-theme-on-surface-variant">Indicateur</text>
                    <text class="text-xs font-bold text-theme-on-surface-variant">Actuelle</text>
                    <text class="text-xs font-bold text-theme-on-surface-variant">Période -1</text>
                </row>
                <row class="w-full justify-between px-4 py-[11] border-t border-theme-outline">
                    <text class="text-sm font-semibold text-theme-on-surface">CA</text>
                    <text class="text-sm font-bold text-theme-on-surface">{{ number_format($metrics['current']['revenue'], 2, ',', ' ') }} €</text>
                    <text class="text-xs text-theme-on-surface-variant">{{ number_format($metrics['previous']['revenue'], 2, ',', ' ') }} €</text>
                </row>
                <row class="w-full justify-between px-4 py-[11] border-t border-theme-outline">
                    <text class="text-sm font-semibold text-theme-on-surface">Commandes</text>
                    <text class="text-sm font-bold text-theme-on-surface">{{ number_format($metrics['current']['orders'], 0, ',', ' ') }}</text>
                    <text class="text-xs text-theme-on-surface-variant">{{ number_format($metrics['previous']['orders'], 0, ',', ' ') }}</text>
                </row>
                <row class="w-full justify-between px-4 py-[11] border-t border-theme-outline">
                    <text class="text-sm font-semibold text-theme-on-surface">Panier moyen</text>
                    <text class="text-sm font-bold text-theme-on-surface">{{ number_format($metrics['current']['avg_order'], 2, ',', ' ') }} €</text>
                    <text class="text-xs text-theme-on-surface-variant">{{ number_format($metrics['previous']['avg_order'], 2, ',', ' ') }} €</text>
                </row>
                <row class="w-full justify-between px-4 py-[11] border-t border-theme-outline">
                    <text class="text-sm font-semibold text-theme-on-surface">Nouveaux clients</text>
                    <text class="text-sm font-bold text-theme-on-surface">{{ number_format($metrics['current']['new_customers'], 0, ',', ' ') }}</text>
                    <text class="text-xs text-theme-on-surface-variant">{{ number_format($metrics['previous']['new_customers'], 0, ',', ' ') }}</text>
                </row>
            </column>
        @endif

        <text class="text-base font-semibold text-theme-on-background mt-[10]">Évolution du CA</text>
        <row class="w-full items-end gap-[6] h-[90] rounded-lg border border-theme-outline bg-theme-surface p-[14]">
            @foreach ($caChart['values'] as $i => $value)
                <column ref="sales-ca-bar-{{ $i }}" class="flex-1 rounded-t bg-theme-primary" height="{{ $this->barHeightPx($caChart, $i, 90) }}" />
            @endforeach
        </row>

        <text class="text-base font-semibold text-theme-on-background mt-[10]">Panier moyen</text>
        <row class="w-full items-end gap-[6] h-[70] rounded-lg border border-theme-outline bg-theme-surface p-[14]">
            @foreach ($basketChart['values'] as $i => $value)
                <column ref="sales-basket-bar-{{ $i }}" class="flex-1 rounded-t bg-[#c9a97a]" height="{{ $this->barHeightPx($basketChart, $i, 70) }}" />
            @endforeach
        </row>

        <text class="text-base font-semibold text-theme-on-background mt-[10]">CA par catégorie</text>
        <column class="w-full gap-2">
            @forelse ($categoryBreakdown as $cat)
                <column class="w-full gap-2 rounded-lg border border-theme-outline bg-theme-surface p-[12]">
                    <row class="w-full justify-between">
                        <text class="text-sm font-semibold text-theme-on-surface">{{ $cat['label'] }}</text>
                        <text class="text-sm font-semibold text-theme-on-surface">{{ number_format($cat['amount'], 2, ',', ' ') }} € · {{ $cat['pct'] }}%</text>
                    </row>
                    <row class="w-full h-[6] rounded-full bg-theme-surface-variant">
                        <column ref="sales-category-fill-{{ $loop->index }}" class="h-full rounded-full bg-theme-primary" width="{{ $cat['pct'] }}%" />
                    </row>
                </column>
            @empty
                <text ref="sales-category-empty" class="text-sm text-theme-on-surface-variant">Aucune donnée de catégorie.</text>
            @endforelse
        </column>

        <text class="text-base font-semibold text-theme-on-background mt-[10]">Top produits vendus</text>
        <column class="w-full gap-2">
            @forelse ($topProducts as $product)
                <row class="w-full items-center gap-3 rounded-lg border border-theme-outline bg-theme-surface p-[11]">
                    <column class="items-center justify-center h-[26] w-[26] rounded-full bg-theme-surface-variant">
                        <text class="text-xs font-bold text-theme-primary">{{ $loop->iteration }}</text>
                    </column>
                    <column class="flex-1 gap-1">
                        <text class="text-sm font-semibold text-theme-on-surface">{{ $product['name'] }}</text>
                        <text class="text-xs text-theme-on-surface-variant">{{ number_format($product['sales'], 0, ',', ' ') }} unités vendues</text>
                    </column>
                    <text class="text-sm font-bold text-theme-on-surface">{{ number_format($product['revenue'], 2, ',', ' ') }} €</text>
                </row>
            @empty
                <text ref="sales-products-empty" class="text-sm text-theme-on-surface-variant">Aucun produit vendu.</text>
            @endforelse
        </column>

        @include('native.partials.shop-switcher-sheet')
        @include('native.partials.account-sheet', [
            'accountInitials' => $this->accountInitials(),
            'accountName' => $this->accountName(),
            'accountEmail' => $this->accountEmail(),
        ])
    </column>
</refreshable>
