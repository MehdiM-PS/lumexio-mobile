<refreshable @refresh="refresh">
    <column fill class="bg-theme-background gap-3 p-4">
        @php $series = $this->chartSeries(); @endphp

        @if ($lastApiError)
            <row ref="forecast-error" class="w-full items-center gap-2 rounded-lg bg-theme-destructive/15 px-4 py-[10]">
                <text class="flex-1 text-sm text-theme-destructive">{{ $lastApiError }}</text>
            </row>
        @endif

        <row class="w-full justify-between items-center">
            <text class="text-lg font-bold text-theme-on-background" font="InstrumentSerif-Regular">Prévisions</text>
            @php $headerConfidence = $this->headerConfidence(); @endphp
            @if ($headerConfidence !== null)
                <text ref="forecast-confidence" class="text-xs text-theme-on-surface-variant">Confiance : {{ $headerConfidence }}%</text>
            @endif
        </row>

        @if ($selectedProductId !== null)
            <row class="w-full justify-between items-center">
                <text ref="forecast-selected-product" class="text-sm font-semibold text-theme-on-surface">{{ $selectedProductName }}</text>
                <button ref="forecast-clear-product" variant="ghost" size="sm" @press="clearProduct">Retour à la vue globale</button>
            </row>
        @endif

        {{-- The readout that used to live in a <text> above the chart is now
        drawn inside it: the chart is a web view, so scrubbing it moves a
        crosshair and updates a tooltip without a bridge round-trip per
        touch. --}}
        @if (count($series) > 0)
            <native:lumexio-chart
                ref="forecast-chart"
                type="area"
                :labels="$this->chartLabels()"
                :series="$this->chartDatasets()"
                :focus="$this->chartFocus()"
                unit=" €"
                :decimals="2"
                a11y-label="Évolution du chiffre d’affaires : réalisé et prévu"
                class="w-full h-[180]"
            />
        @endif

        <text class="text-base font-bold text-theme-on-surface pt-2">Stock &amp; réapprovisionnement</text>

        <outlined-text-input ref="stock-search-input" native:model.debounce.400ms="stockSearch" placeholder="Rechercher par nom ou référence" />

        <column class="w-full gap-1">
            <pressable ref="stock-category-dropdown-toggle" class="w-full rounded-lg bg-theme-surface-variant px-3 py-[10]" @press="toggleStockCategoryDropdown">
                <row class="w-full items-center">
                    <text class="flex-1 text-sm font-semibold text-theme-on-surface">
                        {{ $stockCategoryId !== null ? (collect($stockCategories)->firstWhere('id', $stockCategoryId)['name'] ?? 'Catégorie') : 'Toutes les catégories' }}
                    </text>
                </row>
            </pressable>

            @if ($stockCatOpen)
                <column ref="stock-category-dropdown" class="w-full gap-1 rounded-lg bg-theme-surface border border-theme-outline p-2">
                    <outlined-text-input ref="stock-category-search-input" native:model.debounce.200ms="stockCategorySearch" placeholder="Rechercher une catégorie" />

                    <pressable ref="stock-category-option-all" class="w-full px-3 py-[10]" @press="selectStockCategory(null)">
                        <text class="text-sm font-semibold text-theme-on-surface">Toutes les catégories</text>
                    </pressable>
                    @foreach ($this->filteredStockCategories() as $category)
                        <pressable ref="stock-category-option-{{ $category['id'] }}" class="w-full px-3 py-[10]" @press="selectStockCategory({{ $category['id'] }})">
                            <text class="text-sm font-semibold text-theme-on-surface">{{ $category['name'] }}</text>
                        </pressable>
                    @endforeach
                </column>
            @endif
        </column>

        <row class="w-full items-center justify-between rounded-lg bg-theme-surface-variant px-3 py-[10]">
            <text class="text-sm font-semibold text-theme-on-surface">Masquer les produits inactifs</text>
            <toggle ref="stock-toggle-hide-inactive" a11y-label="Masquer les produits inactifs" native:model="stockHideInactive" />
        </row>
        <row class="w-full items-center justify-between rounded-lg bg-theme-surface-variant px-3 py-[10]">
            <text class="text-sm font-semibold text-theme-on-surface">Masquer les produits en rupture</text>
            <toggle ref="stock-toggle-hide-oos" a11y-label="Masquer les produits en rupture" native:model="stockHideOOS" />
        </row>

        {{-- A narrow phone width can't fit a 5-column table without either
        cramming cell text into unreadable slivers or letting long product
        names/references wrap and collide with neighboring cells — so stock
        items render as one field per line inside a card instead of table
        columns, and sorting moves to a horizontal row of tappable pills
        mirroring the mockup's chip filters above. Each pill is a plain
        `<pressable>` (not a `<chip>`) bound via `@press`, not `@change` —
        `<chip>`'s two-way native:model binding round-trips through the
        client's own local selected state (see the toggle/chip echo-loop
        fixes elsewhere on this screen and in Sales/Alerts/Recommendations),
        which would fight the "tap the active column again to flip
        direction" behavior setStockSort() relies on. A plain press has no
        such echo, so setStockSort() keeps working exactly as before. --}}
        <text class="text-xs font-bold text-theme-on-surface-variant">Trier par</text>
        <scroll-view horizontal class="gap-2">
            @php
                $stockSortOptions = [
                    'name' => 'Nom',
                    'stock' => 'Stock',
                    'avg_sales' => 'Vente moy./mois',
                    'days_left' => 'Jrs avant rupture',
                ];
            @endphp
            @foreach ($stockSortOptions as $column => $label)
                <pressable
                    ref="stock-sort-{{ $column === 'avg_sales' ? 'avg-sales' : ($column === 'days_left' ? 'days-left' : $column) }}"
                    class="rounded-full px-[12] py-[7] {{ $stockSort === $column ? 'bg-theme-primary' : 'bg-theme-surface-variant' }}"
                    @press="setStockSort('{{ $column }}')"
                >
                    <text class="text-xs font-semibold {{ $stockSort === $column ? 'text-theme-on-primary' : 'text-theme-on-surface' }}">{{ $label }}{{ $stockSort === $column ? ($stockDir === 'asc' ? ' ↑' : ' ↓') : '' }}</text>
                </pressable>
            @endforeach
        </scroll-view>
        {{-- Demande 30j: data-only, not sortable — the /products endpoint
        has no server-side sort for it (SERVER_SORTABLE_COLUMNS) and there's
        no client-side-sort need like avg_sales, so it gets no pill above;
        it still shows on every card below. --}}

        <column class="w-full gap-2">
            @forelse ($stockItems as $item)
                <pressable
                    ref="stock-row-{{ $item['id'] }}"
                    class="w-full items-start gap-2 rounded-lg border border-theme-outline bg-theme-surface px-4 py-[12]"
                    @press="selectStockItem({{ $item['id'] }})"
                >
                    <row class="w-full justify-between items-start">
                        <column class="flex-1 gap-0">
                            <text class="text-sm font-bold text-theme-on-surface">{{ $item['name'] ?? '' }}</text>
                            <text class="text-xs text-theme-on-surface-variant">{{ $item['reference'] ?? '' }}</text>
                        </column>
                        <text ref="stock-row-{{ $item['id'] }}-quantity" class="text-sm font-semibold {{ $this->stockQuantityColorClass($item) }}">{{ $item['quantity'] ?? 0 }} en stock</text>
                    </row>
                    <row class="w-full justify-between">
                        <column class="gap-0">
                            <text class="text-[10] text-theme-on-surface-variant">Vente moy./mois</text>
                            <text ref="stock-row-{{ $item['id'] }}-avg-sales" class="text-sm font-semibold text-theme-on-surface">{{ $item['average_monthly_sales'] ?? 0 }}</text>
                        </column>
                        <column class="gap-0">
                            <text class="text-[10] text-theme-on-surface-variant">Demande 30j</text>
                            <text ref="stock-row-{{ $item['id'] }}-demand" class="text-sm font-semibold text-theme-on-surface">{{ $item['demand_30d'] ?? 0 }}</text>
                        </column>
                        <column class="items-end gap-0">
                            <text class="text-[10] text-theme-on-surface-variant">Jrs avant rupture</text>
                            <text ref="stock-row-{{ $item['id'] }}-days-left" class="text-sm font-semibold {{ $this->stockDaysLeftColorClass($item) }}">{{ $this->stockDaysLeftText($item) }}</text>
                        </column>
                    </row>
                </pressable>
            @empty
                <text ref="stock-empty" class="px-3 py-[16] text-center text-sm text-theme-on-surface-variant">Aucun produit ne correspond aux filtres.</text>
            @endforelse
        </column>

        <row class="w-full items-center justify-between">
            <text ref="stock-page-range" class="text-xs font-semibold text-theme-on-surface-variant">{{ $this->stockRangeLabel() }}</text>
            <row class="gap-2">
                <button ref="stock-page-prev" variant="ghost" size="sm" icon="chevron.left" a11y-label="Page précédente" @press="stockPagePrev" :disabled="$stockPage <= 1" />
                <button ref="stock-page-next" variant="ghost" size="sm" icon="chevron.right" a11y-label="Page suivante" @press="stockPageNext" :disabled="$stockPage >= $stockLastPage" />
            </row>
        </row>

        <outlined-text-input ref="forecast-product-search" native:model.debounce.400ms="productSearch" label="Rechercher un produit" placeholder="Nom ou référence" />

        @if (count($productResults) > 0)
            <column class="w-full gap-1">
                @foreach ($productResults as $product)
                    <pressable
                        ref="forecast-product-result-{{ $product['id'] }}"
                        class="w-full rounded-lg bg-theme-surface-variant px-4 py-[8]"
                        @press="selectProduct({{ $product['id'] }})"
                    >
                        <text class="text-sm text-theme-on-surface">{{ $product['name'] }}</text>
                    </pressable>
                @endforeach
            </column>
        @endif

        @include('native.partials.shop-switcher-sheet')
        @include('native.partials.account-sheet', [
            'accountInitials' => $this->accountInitials(),
            'accountName' => $this->accountName(),
            'accountEmail' => $this->accountEmail(),
        ])
    </column>
</refreshable>
