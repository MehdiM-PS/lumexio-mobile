{{--
    `back` (show-navigation-icon) IS required here, despite this screen only
    ever being reached via navigate() (i.e. always pushed). Source-verified
    (no on-device test — no simulator in this environment):

    - NativeRootStackRenderer.swift only draws a manual back chevron when
      `showBack && isRoot` — pushed levels (`isRoot == false`) get the
      chevron for free from SwiftUI's own NavigationStack, no prop needed.
    - BUT this screen is a top-level route with NO NativeLayout, so it
      publishes its own `native_root_stack` sentinel — a DIFFERENT
      NavigationStack from the tab it was pushed from (Orders, which
      renders via `native_root_tabs` + PerTabNavigationCoordinator).
    - NativeElementBridge.swift explicitly resets the singleton
      NavigationCoordinator (`isFreshStackMount` → `NavigationCoordinator
      .shared.reset()`) every time the root sentinel TYPE changes to
      `native_root_stack` from something else (tabs, WebView, nothing) —
      not just on the app's first-ever publish. Since this screen is
      always reached FROM the tabs chrome (Orders), every navigation here
      is a fresh stack mount: `rootUri` gets cleared, then
      NavigationCoordinator.swift's `receive()` seeds it from the very
      next uri it gets — OrderDetail's own. So this screen is `isRoot:
      true` on ITS stack on every visit, even though PHP's router
      considers it "pushed".
    - `isRoot: true` + `back` unset (defaults false) => NO chevron at all
      and no way back. Hence: explicit `back` here.
--}}
<top-bar title="{{ $order['reference'] ?? 'Commande' }}" back />

<refreshable @refresh="loadDetail">
<column fill class="bg-theme-background gap-3 p-4">
    @if ($lastApiError)
        <row ref="order-detail-error" class="w-full items-center gap-2 rounded-lg bg-theme-destructive/15 px-4 py-[10]">
            <text class="flex-1 text-sm text-theme-destructive">{{ $lastApiError }}</text>
        </row>
    @endif

    <row class="w-full justify-between">
        <text class="text-xl font-bold text-theme-on-background" font="InstrumentSerif-Regular">
            {{ $order['reference'] ?? '' }}
        </text>
        @if (! empty($order['status_label']))
            <row ref="order-detail-status-badge" class="items-center rounded-full px-[8] py-[2] bg-[{{ $order['status_color'] }}]">
                <text ref="order-detail-status" class="text-xs font-medium text-[{{ $order['status_text_color'] }}]">{{ $order['status_label'] }}</text>
            </row>
        @endif
    </row>

    @if (! empty($order['order_date']))
        <text class="text-sm text-theme-on-surface-variant">
            {{ \Carbon\Carbon::parse($order['order_date'])->format('d/m/Y H:i') }}
        </text>
    @endif

    @if (! empty($order['customer']))
        <text class="text-base font-semibold text-theme-on-background">Client</text>
        <text class="text-sm text-theme-on-surface">
            {{ trim($order['customer']['firstname'].' '.$order['customer']['lastname']) }}
        </text>
        <text class="text-sm text-theme-on-surface-variant">{{ $order['customer']['email'] }}</text>
    @endif

    @if (! empty($order['items']))
        <text class="text-base font-semibold text-theme-on-background">Articles</text>
        <column class="w-full gap-2">
            @foreach ($order['items'] as $item)
                <row class="w-full justify-between rounded-lg bg-theme-surface-variant px-4 py-[10]">
                    <column class="gap-0">
                        <text ref="order-detail-item-{{ $item['id'] }}-name" class="text-sm font-medium text-theme-on-surface">
                            {{ $item['product_name'] }}
                        </text>
                        <text class="text-xs text-theme-on-surface-variant">
                            {{ $item['quantity'] }} × {{ number_format($item['unit_price'], 2, ',', ' ') }} €
                        </text>
                    </column>
                    <text class="text-sm font-semibold text-theme-on-surface">
                        {{ number_format($item['total_price'], 2, ',', ' ') }} €
                    </text>
                </row>
            @endforeach
        </column>
    @endif

    <text class="text-base font-semibold text-theme-on-background">Total</text>
    <row class="w-full justify-between">
        <text class="text-sm text-theme-on-surface-variant">Total TTC</text>
        <text ref="order-detail-total-ttc" class="text-sm font-semibold text-theme-on-surface">
            {{ number_format($order['total_paid'] ?? 0, 2, ',', ' ') }} €
        </text>
    </row>

    @if (array_key_exists('margin', $order))
        <text class="text-base font-semibold text-theme-on-background">Marge</text>
        <row class="w-full justify-between">
            <text class="text-sm text-theme-on-surface-variant">Coût d'achat</text>
            <text class="text-sm text-theme-on-surface">{{ number_format($order['purchase_cost'], 2, ',', ' ') }} €</text>
        </row>
        <row class="w-full justify-between">
            <text class="text-sm text-theme-on-surface-variant">Frais fixe</text>
            <text class="text-sm text-theme-on-surface">{{ number_format($order['fixed_fee'], 2, ',', ' ') }} €</text>
        </row>
        {{-- Total HT sits directly above the Marge row (rather than up in
             the Total block, next to TTC) because it — not TTC — is the
             actual denominator of margin_rate. Keeping the two figures
             adjacent lets a merchant verify margin / total_ht ≈ margin_rate
             at a glance, instead of naturally trying margin / TTC and
             concluding the math is broken. --}}
        <row class="w-full justify-between">
            <text class="text-sm text-theme-on-surface-variant">Total HT</text>
            <text ref="order-detail-total-ht" class="text-sm font-semibold text-theme-on-surface">
                {{ number_format($order['total_paid_tax_excl'] ?? 0, 2, ',', ' ') }} €
            </text>
        </row>
        <row class="w-full justify-between">
            <text class="text-sm text-theme-on-surface-variant">Marge</text>
            <text class="text-sm font-semibold {{ $order['margin'] >= 0 ? 'text-theme-primary' : 'text-theme-destructive' }}">
                {{ number_format($order['margin'], 2, ',', ' ') }} €
                @if ($order['margin_rate'] !== null)
                    ({{ number_format($order['margin_rate'], 1, ',', ' ') }}%)
                @endif
            </text>
        </row>
    @endif
</column>
</refreshable>
