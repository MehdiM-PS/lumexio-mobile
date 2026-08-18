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

        @if (count($series) > 0)
            @if ($selectedBarIndex !== null && isset($series[$selectedBarIndex]))
                @php $selected = $series[$selectedBarIndex]; @endphp
                <text ref="forecast-tooltip" class="text-xs text-theme-on-surface-variant">
                    {{ $selected['date'] }} — {{ $selected['type'] === 'historical' ? 'Réalisé' : 'Prévu' }} : {{ number_format($selected['revenue'], 2, ',', ' ') }} €
                    @if ($selected['confidence'] !== null)
                        (confiance {{ $selected['confidence'] }}%)
                    @endif
                </text>
            @endif

            <row class="w-full items-end gap-1 h-[80]">
                @foreach ($series as $index => $bar)
                    <rect
                        ref="forecast-bar-{{ $index }}"
                        class="flex-1 rounded-sm {{ $selectedBarIndex === $index ? 'bg-theme-primary' : ($bar['type'] === 'historical' ? 'bg-theme-primary/50' : 'bg-theme-accent/60') }}"
                        height="{{ $this->barHeight($bar['revenue']) }}"
                        a11y-label="{{ $bar['date'] }} — {{ $bar['type'] === 'historical' ? 'Réalisé' : 'Prévu' }} : {{ number_format($bar['revenue'], 2, ',', ' ') }} €"
                        @press="selectBar({{ $index }})"
                    />
                @endforeach
            </row>
        @endif

        <text class="text-base font-bold text-theme-on-surface pt-2">Stock &amp; réapprovisionnement</text>

        <outlined-text-input ref="stock-search-input" native:model.debounce.400ms="stockSearch" placeholder="Rechercher par nom ou référence" />

        <column class="w-full gap-1">
            <pressable ref="stock-category-dropdown-toggle" class="w-full flex-row items-center rounded-lg bg-theme-surface-variant px-3 py-[10]" @press="toggleStockCategoryDropdown">
                <text class="flex-1 text-sm font-semibold text-theme-on-surface">
                    {{ $stockCategoryId !== null ? (collect($stockCategories)->firstWhere('id', $stockCategoryId)['name'] ?? 'Catégorie') : 'Toutes les catégories' }}
                </text>
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
            <toggle ref="stock-toggle-hide-inactive" a11y-label="Masquer les produits inactifs" :value="$stockHideInactive" @change="toggleStockHideInactive" />
        </row>
        <row class="w-full items-center justify-between rounded-lg bg-theme-surface-variant px-3 py-[10]">
            <text class="text-sm font-semibold text-theme-on-surface">Masquer les produits en rupture</text>
            <toggle ref="stock-toggle-hide-oos" a11y-label="Masquer les produits en rupture" :value="$stockHideOOS" @change="toggleStockHideOOS" />
        </row>

        {{-- Column widths approximate the mockup's `1.7fr 0.8fr 1fr 1fr 1.1fr`
        grid: this UI framework's TailwindParser has no fractional flex-grow
        (only the fixed `flex-1` utility) and no percentage width (`w-[N]`
        is an absolute size), so an exact fr-ratio grid isn't representable
        — same category of gap as shop-switcher-sheet.blade.php's dashed-
        border note. Produit keeps `flex-1` (it carries the largest share);
        the other four get fixed widths in the same relative order as their
        fr values (0.8 : 1 : 1 : 1.1). The header row and each data row use
        the identical width class per column so the two line up. --}}
        <column class="w-full gap-0 rounded-lg bg-theme-surface border border-theme-outline">
            <row class="w-full gap-2 px-3 py-[8]">
                <pressable ref="stock-sort-name" class="flex-1" @press="setStockSort('name')">
                    <text class="text-xs font-bold uppercase text-theme-on-surface-variant">Produit{{ $stockSort === 'name' ? ($stockDir === 'asc' ? ' ↑' : ' ↓') : '' }}</text>
                </pressable>
                <pressable ref="stock-sort-stock" class="w-[40]" @press="setStockSort('stock')">
                    <text class="text-xs font-bold uppercase text-theme-on-surface-variant">Stock{{ $stockSort === 'stock' ? ($stockDir === 'asc' ? ' ↑' : ' ↓') : '' }}</text>
                </pressable>
                <pressable ref="stock-sort-avg-sales" class="w-[52]" @press="setStockSort('avg_sales')">
                    <text class="text-xs font-bold uppercase text-theme-on-surface-variant">Vente moy./mois{{ $stockSort === 'avg_sales' ? ($stockDir === 'asc' ? ' ↑' : ' ↓') : '' }}</text>
                </pressable>
                {{-- Demande 30j: data-only, not sortable — the /products
                endpoint has no server-side sort for it (SERVER_SORTABLE_COLUMNS)
                and there's no client-side-sort need like avg_sales. Plain
                text, no pressable/@press and no sort arrow, so it doesn't
                present as tappable when it isn't. --}}
                <text class="w-[52] text-xs font-bold uppercase text-theme-on-surface-variant">Demande 30j</text>
                <pressable ref="stock-sort-days-left" class="w-[56]" @press="setStockSort('days_left')">
                    <text class="text-xs font-bold uppercase text-theme-on-surface-variant">Jrs avant rupture{{ $stockSort === 'days_left' ? ($stockDir === 'asc' ? ' ↑' : ' ↓') : '' }}</text>
                </pressable>
            </row>

            @forelse ($stockItems as $item)
                <pressable ref="stock-row-{{ $item['id'] }}" class="w-full flex-row items-center gap-2 border-t border-theme-outline px-3 py-[10]" @press="selectStockItem({{ $item['id'] }})">
                    <column class="flex-1 gap-0">
                        <text class="text-sm font-bold text-theme-on-surface">{{ $item['name'] ?? '' }}</text>
                        <text class="text-xs text-theme-on-surface-variant">{{ $item['reference'] ?? '' }}</text>
                    </column>
                    <text class="w-[40] text-sm font-semibold {{ $this->stockQuantityColorClass($item) }}">{{ $item['quantity'] ?? 0 }}</text>
                    <text class="w-[52] text-sm text-theme-on-surface">{{ $item['average_monthly_sales'] ?? 0 }}</text>
                    <text class="w-[52] text-sm text-theme-on-surface">{{ $item['demand_30d'] ?? 0 }}</text>
                    <text class="w-[56] text-sm font-semibold {{ $this->stockDaysLeftColorClass($item) }}">{{ $this->stockDaysLeftText($item) }}</text>
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
