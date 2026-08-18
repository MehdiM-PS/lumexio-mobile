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

        {{-- Mock (line 231-239): the date-range text and the Comparer
        label+toggle share one justify-between row, with Comparer grouped
        on the right — not "Comparer" alone on the left with the toggle. --}}
        <row class="w-full justify-between items-center">
            <text ref="sales-date-range" class="text-xs font-bold text-theme-on-surface-variant">{{ $this->salesDateRangeLabel() }}</text>
            <row class="items-center gap-2">
                <text class="text-sm font-semibold text-theme-on-surface">Comparer</text>
                <toggle ref="sales-compare-toggle" a11y-label="Comparer" native:model="compareEnabled" />
            </row>
        </row>

        <row class="w-full gap-3">
            <column class="flex-1 gap-1 rounded-lg bg-theme-surface border border-theme-outline p-[14]">
                <text class="text-xs text-theme-on-surface-variant">Chiffre d'affaires</text>
                <text ref="sales-metric-revenue" class="text-xl font-bold text-theme-on-background">{{ number_format($metrics['current']['revenue'], 2, ',', ' ') }} €</text>
                <text class="text-xs font-bold {{ $metrics['changes']['revenue'] >= 0 ? 'text-theme-success' : 'text-theme-destructive' }}">{{ $metrics['changes']['revenue'] >= 0 ? '+' : '' }}{{ number_format($metrics['changes']['revenue'], 1, ',', ' ') }}% vs période -1</text>
            </column>
            <column class="flex-1 gap-1 rounded-lg bg-theme-surface border border-theme-outline p-[14]">
                <text class="text-xs text-theme-on-surface-variant">Commandes</text>
                <text ref="sales-metric-orders" class="text-xl font-bold text-theme-on-background">{{ number_format($metrics['current']['orders'], 0, ',', ' ') }}</text>
                <text class="text-xs font-bold {{ $metrics['changes']['orders'] >= 0 ? 'text-theme-success' : 'text-theme-destructive' }}">{{ $metrics['changes']['orders'] >= 0 ? '+' : '' }}{{ number_format($metrics['changes']['orders'], 1, ',', ' ') }}% vs période -1</text>
            </column>
        </row>
        <row class="w-full gap-3">
            <column class="flex-1 gap-1 rounded-lg bg-theme-surface border border-theme-outline p-[14]">
                <text class="text-xs text-theme-on-surface-variant">Panier moyen</text>
                <text ref="sales-metric-avg-order" class="text-xl font-bold text-theme-on-background">{{ number_format($metrics['current']['avg_order'], 2, ',', ' ') }} €</text>
                <text class="text-xs font-bold {{ $metrics['changes']['avg_order'] >= 0 ? 'text-theme-success' : 'text-theme-destructive' }}">{{ $metrics['changes']['avg_order'] >= 0 ? '+' : '' }}{{ number_format($metrics['changes']['avg_order'], 1, ',', ' ') }}% vs période -1</text>
            </column>
            <column class="flex-1 gap-1 rounded-lg bg-theme-surface border border-theme-outline p-[14]">
                <text class="text-xs text-theme-on-surface-variant">Nouveaux clients</text>
                <text ref="sales-metric-new-customers" class="text-xl font-bold text-theme-on-background">{{ number_format($metrics['current']['new_customers'], 0, ',', ' ') }}</text>
                <text class="text-xs font-bold {{ $metrics['changes']['new_customers'] >= 0 ? 'text-theme-success' : 'text-theme-destructive' }}">{{ $metrics['changes']['new_customers'] >= 0 ? '+' : '' }}{{ number_format($metrics['changes']['new_customers'], 1, ',', ' ') }}% vs période -1</text>
            </column>
        </row>

        @if ($compareEnabled)
            <text class="text-base font-semibold text-theme-on-background mt-[10]">Actuelle vs période -1</text>
            <column class="w-full rounded-lg border border-theme-outline bg-theme-surface">
                <row class="w-full px-4 py-[10] bg-theme-surface-variant">
                    <text ref="sales-compare-header-label" class="flex-1 text-xs font-bold text-theme-on-surface-variant">Indicateur</text>
                    <text class="flex-1 text-right text-xs font-bold text-theme-on-surface-variant">Actuelle</text>
                    <text class="flex-1 text-right text-xs font-bold text-theme-on-surface-variant">Période -1</text>
                </row>
                @php
                    // Mock (line 261): the "Période -1" cell nests the raw
                    // previous value above a bold, colored delta line, not
                    // the previous value alone.
                    $compareRows = [
                        ['label' => 'CA', 'current' => number_format($metrics['current']['revenue'], 2, ',', ' ').' €', 'previous' => number_format($metrics['previous']['revenue'], 2, ',', ' ').' €', 'change' => $metrics['changes']['revenue']],
                        ['label' => 'Commandes', 'current' => number_format($metrics['current']['orders'], 0, ',', ' '), 'previous' => number_format($metrics['previous']['orders'], 0, ',', ' '), 'change' => $metrics['changes']['orders']],
                        ['label' => 'Panier moyen', 'current' => number_format($metrics['current']['avg_order'], 2, ',', ' ').' €', 'previous' => number_format($metrics['previous']['avg_order'], 2, ',', ' ').' €', 'change' => $metrics['changes']['avg_order']],
                        ['label' => 'Nouveaux clients', 'current' => number_format($metrics['current']['new_customers'], 0, ',', ' '), 'previous' => number_format($metrics['previous']['new_customers'], 0, ',', ' '), 'change' => $metrics['changes']['new_customers']],
                    ];
                @endphp
                @foreach ($compareRows as $row)
                    <row class="w-full px-4 py-[11] border-t border-theme-outline items-center">
                        <text class="flex-1 text-sm font-semibold text-theme-on-surface">{{ $row['label'] }}</text>
                        <text class="flex-1 text-right text-sm font-bold text-theme-on-surface">{{ $row['current'] }}</text>
                        <column class="flex-1 items-end gap-0">
                            <text class="text-xs text-theme-on-surface-variant">{{ $row['previous'] }}</text>
                            <text ref="sales-compare-{{ $loop->index }}-delta" class="text-xs font-bold {{ $row['change'] >= 0 ? 'text-theme-success' : 'text-theme-destructive' }}">{{ $row['change'] >= 0 ? '+' : '' }}{{ number_format($row['change'], 1, ',', ' ') }}%</text>
                        </column>
                    </row>
                @endforeach
            </column>
        @endif

        <text class="text-base font-semibold text-theme-on-background mt-[10]">Évolution du CA</text>
        <column class="w-full rounded-lg border border-theme-outline bg-theme-surface p-[14]">
            <row class="w-full items-end gap-[6] h-[90]">
                @foreach ($caChart['values'] as $i => $value)
                    <column ref="sales-ca-bar-{{ $i }}" class="flex-1 rounded-t bg-theme-primary" height="{{ $this->barHeightPx($caChart, $i, 90) }}" a11y-label="{{ $caChart['labels'][$i] ?? '' }} — {{ number_format($value, 2, ',', ' ') }} €" />
                @endforeach
            </row>
            <row class="w-full gap-[6] mt-[6]">
                @foreach ($caChart['labels'] as $i => $label)
                    <text ref="sales-ca-bar-{{ $i }}-label" class="flex-1 text-center text-[10] text-theme-on-surface-variant">{{ $label }}</text>
                @endforeach
            </row>
        </column>

        <text class="text-base font-semibold text-theme-on-background mt-[10]">Panier moyen</text>
        <column class="w-full rounded-lg border border-theme-outline bg-theme-surface p-[14]">
            <row class="w-full items-end gap-[6] h-[70]">
                @foreach ($basketChart['values'] as $i => $value)
                    <column ref="sales-basket-bar-{{ $i }}" class="flex-1 rounded-t bg-[#c9a97a]" height="{{ $this->barHeightPx($basketChart, $i, 70) }}" a11y-label="{{ $basketChart['labels'][$i] ?? '' }} — {{ number_format($value, 2, ',', ' ') }} €" />
                @endforeach
            </row>
            <row class="w-full gap-[6] mt-[6]">
                @foreach ($basketChart['labels'] as $i => $label)
                    <text ref="sales-basket-bar-{{ $i }}-label" class="flex-1 text-center text-[10] text-theme-on-surface-variant">{{ $label }}</text>
                @endforeach
            </row>
        </column>

        <text class="text-base font-semibold text-theme-on-background mt-[10]">CA par catégorie</text>
        <column class="w-full gap-2">
            @forelse ($categoryBreakdown as $cat)
                <column class="w-full gap-2 rounded-lg border border-theme-outline bg-theme-surface p-[12]">
                    <row class="w-full justify-between">
                        <text class="text-sm font-semibold text-theme-on-surface">{{ $cat['label'] }}</text>
                        <text class="text-sm font-semibold text-theme-on-surface">{{ number_format($cat['amount'], 2, ',', ' ') }} € · {{ $cat['pct'] }}%</text>
                    </row>
                    <row class="w-full h-[6] rounded-full bg-theme-surface-variant">
                        {{-- max(2, ...): the width-percent branch is also >0-guarded natively
                             (see barHeightPx()'s docblock) — a literal 0% would drop the width
                             constraint entirely rather than rendering an empty fill. --}}
                        <column ref="sales-category-fill-{{ $loop->index }}" class="h-full rounded-full bg-theme-primary" width="{{ max(2, $cat['pct']) }}%" />
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
