{{--
    `back` (show-navigation-icon) IS required here, despite this screen only
    ever being reached via navigate() (i.e. always pushed). Source-verified
    (no on-device test — no simulator in this environment):

    - NativeRootStackRenderer.swift only draws a manual back chevron when
      `showBack && isRoot` — pushed levels (`isRoot == false`) get the
      chevron for free from SwiftUI's own NavigationStack, no prop needed.
    - BUT this screen is a top-level route with NO NativeLayout, so it
      publishes its own `native_root_stack` sentinel — a DIFFERENT
      NavigationStack from the tab it was pushed from (Stock, which
      renders via `native_root_tabs` + PerTabNavigationCoordinator).
    - NativeElementBridge.swift explicitly resets the singleton
      NavigationCoordinator (`isFreshStackMount` → `NavigationCoordinator
      .shared.reset()`) every time the root sentinel TYPE changes to
      `native_root_stack` from something else (tabs, WebView, nothing) —
      not just on the app's first-ever publish. Since this screen is
      always reached FROM the tabs chrome (Stock), every navigation here
      is a fresh stack mount: `rootUri` gets cleared, then
      NavigationCoordinator.swift's `receive()` seeds it from the very
      next uri it gets — ItemDetail's own. So this screen is `isRoot:
      true` on ITS stack on every visit, even though PHP's router
      considers it "pushed".
    - `isRoot: true` + `back` unset (defaults false) => NO chevron at all
      and no way back. Hence: explicit `back` here.
--}}
<top-bar title="{{ $item['name'] ?? 'Détail' }}" back />

<refreshable @refresh="loadHistory">
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

    {{-- Bars, tooltip and per-bar selection all live inside the chart's web
    view now — tapping a bar highlights it and shows its date and quantity
    without a round-trip through PHP. --}}
    @if (count($historyQuantities) > 0)
        <text class="text-base font-semibold text-theme-on-background">Historique de stock</text>
        <native:lumexio-chart
            ref="stock-history-chart"
            type="bar"
            :labels="$historyLabels"
            :series="$this->historySeries()"
            unit=" en stock"
            a11y-label="Historique de stock"
            class="w-full h-[120]"
        />
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
</refreshable>
