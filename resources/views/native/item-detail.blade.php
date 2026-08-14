<column fill class="bg-theme-background gap-3 p-4">
    @if ($lastApiError)
        <row ref="item-detail-error" class="w-full items-center gap-2 rounded-lg bg-theme-destructive/15 px-4 py-[10]">
            <text class="flex-1 text-sm text-theme-destructive">{{ $lastApiError }}</text>
        </row>
    @endif

    <text class="text-xl font-bold text-theme-on-background" font="InstrumentSerif-Regular">
        {{ $item['name'] }}
    </text>
    <text class="text-sm text-theme-on-surface-variant">{{ $item['reference'] }}</text>

    <row class="w-full justify-between">
        <text class="text-sm text-theme-on-surface-variant">Stock</text>
        <text class="text-sm font-semibold text-theme-on-surface">{{ $item['quantity'] }} / {{ $item['low_stock_threshold'] }}</text>
    </row>

    @if (! empty($item['supplier_name']))
        <row class="w-full justify-between">
            <text class="text-sm text-theme-on-surface-variant">Fournisseur</text>
            <text class="text-sm text-theme-on-surface">{{ $item['supplier_name'] }}</text>
        </row>
    @endif

    @if (! empty($item['category_name']))
        <row class="w-full justify-between">
            <text class="text-sm text-theme-on-surface-variant">Catégorie</text>
            <text class="text-sm text-theme-on-surface">{{ $item['category_name'] }}</text>
        </row>
    @endif

    @if (! empty($item['recommended_reorder_quantity']))
        <row class="w-full justify-between">
            <text class="text-sm text-theme-on-surface-variant">Réappro recommandé</text>
            <text class="text-sm text-theme-on-surface">{{ $item['recommended_reorder_quantity'] }} avant le {{ $item['recommended_reorder_date'] }}</text>
        </row>
    @endif

    @if (count($historyQuantities) > 0)
        <text class="text-base font-semibold text-theme-on-background">Historique de stock</text>
        <canvas class="w-full h-[80]">
            <row class="w-full h-full items-end justify-between gap-2">
                @foreach ($historyQuantities as $value)
                    <rect class="flex-1 rounded-sm bg-theme-primary" height="{{ $this->barHeight($value) }}" />
                @endforeach
            </row>
        </canvas>
    @endif

    @if ($item['type'] === 'product')
        <text class="text-base font-semibold text-theme-on-background">Modifier</text>

        <row class="w-full items-end gap-2">
            <column class="flex-1">
                <outlined-text-input ref="threshold-input" native:model="thresholdInput" label="Seuil d'alerte" keyboard="number" />
            </column>
            <button ref="save-threshold" variant="secondary" @press="saveThreshold">Enregistrer</button>
        </row>

        <row class="w-full items-end gap-2">
            <column class="flex-1">
                <outlined-text-input ref="lead-time-input" native:model="leadTimeInput" label="Délai fournisseur (jours)" keyboard="number" />
            </column>
            <button ref="save-lead-time" variant="secondary" @press="saveLeadTime">Enregistrer</button>
        </row>
    @else
        <text ref="variant-readonly-note" class="text-sm text-theme-on-surface-variant">
            L'édition du seuil et du délai fournisseur n'est pas disponible pour les déclinaisons.
        </text>
    @endif
</column>
