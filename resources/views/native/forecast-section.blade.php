@php $series = $this->chartSeries(); @endphp

<column class="w-full gap-3 p-4">
    @if ($lastApiError)
        <row ref="forecast-error" class="w-full items-center gap-2 rounded-lg bg-theme-destructive/15 px-4 py-[10]">
            <text class="flex-1 text-sm text-theme-destructive">{{ $lastApiError }}</text>
        </row>
    @endif

    <row class="w-full justify-between items-center">
        <text class="text-lg font-bold text-theme-on-background" font="InstrumentSerif-Regular">Prévisions</text>
        @if ($selectedProductId === null)
            <text ref="forecast-confidence" class="text-xs text-theme-on-surface-variant">Confiance : {{ $summary['confidence'] ?? 0 }}%</text>
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
</column>
