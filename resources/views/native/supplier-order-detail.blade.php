{{--
    `back` (show-navigation-icon) IS required here, despite this screen only
    ever being reached via navigate() (i.e. always pushed). Source-verified
    (no on-device test — no simulator in this environment):

    - NativeRootStackRenderer.swift only draws a manual back chevron when
      `showBack && isRoot` — pushed levels (`isRoot == false`) get the
      chevron for free from SwiftUI's own NavigationStack, no prop needed.
    - BUT this screen is a top-level route with NO NativeLayout, so it
      publishes its own `native_root_stack` sentinel — a DIFFERENT
      NavigationStack from the tab it was pushed from (Suppliers, which
      renders via `native_root_tabs` + PerTabNavigationCoordinator).
    - NativeElementBridge.swift explicitly resets the singleton
      NavigationCoordinator (`isFreshStackMount` → `NavigationCoordinator
      .shared.reset()`) every time the root sentinel TYPE changes to
      `native_root_stack` from something else (tabs, WebView, nothing) —
      not just on the app's first-ever publish. Since this screen is
      always reached FROM the tabs chrome (Suppliers), every navigation
      here is a fresh stack mount: `rootUri` gets cleared, then
      NavigationCoordinator.swift's `receive()` seeds it from the very
      next uri it gets — SupplierOrderDetail's own. So this screen is
      `isRoot: true` on ITS stack on every visit, even though PHP's
      router considers it "pushed".
    - `isRoot: true` + `back` unset (defaults false) => NO chevron at all
      and no way back. Hence: explicit `back` here.
--}}
<top-bar title="{{ $order['reference'] ?? 'Commande' }}" back />

<refreshable @refresh="loadDetail">
<column fill class="bg-theme-background gap-3 p-4">
    @if ($lastApiError)
        <row ref="so-detail-error" class="w-full items-center gap-2 rounded-lg bg-theme-destructive/15 px-4 py-[10]">
            <text class="flex-1 text-sm text-theme-destructive">{{ $lastApiError }}</text>
        </row>
    @endif

    <row class="w-full justify-between">
        <text ref="so-detail-reference" class="text-xl font-bold text-theme-on-background" font="InstrumentSerif-Regular">{{ $order['reference'] ?? '' }}</text>
        @if (! empty($order['status_label']))
            <row class="items-center rounded-full px-[8] py-[2] bg-[{{ $order['status_color'] }}]">
                <text class="text-xs font-medium">{{ $order['status_label'] }}</text>
            </row>
        @endif
    </row>

    @if (! empty($order['supplier']))
        <text class="text-sm text-theme-on-surface-variant">{{ $order['supplier']['name'] }}</text>
    @endif

    @if (! empty($order['expected_delivery_date']))
        <text class="text-sm text-theme-on-surface-variant">Livraison prévue : {{ $order['expected_delivery_date'] }}</text>
    @endif

    @if (! empty($order['items']))
        <text class="text-base font-semibold text-theme-on-background">Articles</text>
        <column class="w-full gap-2">
            @foreach ($order['items'] as $item)
                <row class="w-full justify-between rounded-lg bg-theme-surface-variant px-4 py-[10]">
                    <column class="gap-0">
                        <text ref="so-detail-item-{{ $item['id'] }}-name" class="text-sm font-medium text-theme-on-surface">
                            {{ $item['variant_name'] ?? $item['product_name'] }}
                        </text>
                        <text class="text-xs text-theme-on-surface-variant">
                            {{ $item['quantity'] }} × {{ number_format($item['unit_cost'], 2, ',', ' ') }} € · reçu {{ $item['received_quantity'] }}/{{ $item['quantity'] }}
                        </text>
                    </column>
                    <text class="text-sm font-semibold text-theme-on-surface">{{ number_format($item['total_cost'], 2, ',', ' ') }} €</text>
                </row>
            @endforeach
        </column>
    @endif

    @if (array_key_exists('margin', $order) && $order['margin'] !== null)
        <text class="text-base font-semibold text-theme-on-background">Marge</text>
        <row class="w-full justify-between">
            <text class="text-sm text-theme-on-surface-variant">Marge</text>
            <text class="text-sm font-semibold {{ $order['margin']['margin_euros'] >= 0 ? 'text-theme-primary' : 'text-theme-destructive' }}">
                {{ number_format($order['margin']['margin_euros'], 2, ',', ' ') }} €
                @if ($order['margin']['margin_percent'] !== null)
                    ({{ number_format($order['margin']['margin_percent'], 1, ',', ' ') }}%)
                @endif
            </text>
        </row>
    @endif

    @if (! empty($order['status_histories']))
        <text class="text-base font-semibold text-theme-on-background">Historique</text>
        <column class="w-full gap-2">
            @foreach ($order['status_histories'] as $history)
                <text class="text-xs text-theme-on-surface-variant">
                    {{ $history['to_status'] }} — {{ \Carbon\Carbon::parse($history['created_at'])->format('d/m/Y H:i') }}
                </text>
            @endforeach
        </column>
    @endif

    <row class="w-full gap-2">
        @foreach (($order['allowed_transitions'] ?? []) as $status)
            @if ($status !== 'recue')
                <button ref="so-detail-transition-{{ $status }}" variant="secondary" @press="transitionTo('{{ $status }}')">{{ ucfirst(str_replace('_', ' ', $status)) }}</button>
            @endif
        @endforeach
    </row>

    @if (in_array($order['status'] ?? null, \App\NativeComponents\Screens\SupplierOrderDetail::PENDING_STATUSES, true))
        <button ref="so-detail-receive" variant="primary" @press="markFullyReceived">Recevoir entièrement</button>
    @endif

    <button ref="so-detail-duplicate" variant="ghost" @press="duplicate">Dupliquer</button>
</column>
</refreshable>
