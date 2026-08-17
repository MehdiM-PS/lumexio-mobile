<refreshable @refresh="refresh">
    <column fill class="bg-theme-background gap-3 p-4">
        @if ($lastApiError)
            <row ref="orders-error" class="w-full items-center gap-2 rounded-lg bg-theme-destructive/15 px-4 py-[10]">
                <text class="flex-1 text-sm text-theme-destructive">{{ $lastApiError }}</text>
            </row>
        @endif

        <outlined-text-input
            ref="orders-search"
            native:model.debounce.400ms="search"
            label="Rechercher"
            placeholder="Référence, nom ou email client"
        />

        <column class="w-full gap-2">
            @forelse ($orders as $order)
                <pressable
                    ref="order-{{ $order['id'] }}"
                    class="w-full items-start gap-1 rounded-lg border border-theme-outline bg-theme-surface px-4 py-[12]"
                    @press="selectOrder({{ $order['id'] }})"
                >
                    <row class="w-full justify-between">
                        <text ref="order-{{ $order['id'] }}-reference" class="text-base font-semibold text-theme-on-surface">
                            {{ $order['reference'] }}
                        </text>
                        <row ref="order-{{ $order['id'] }}-badge" class="items-center rounded-full px-[8] py-[2] bg-[{{ $order['status_color'] }}]">
                            <text ref="order-{{ $order['id'] }}-status" class="text-xs font-medium text-[{{ $order['status_text_color'] }}]">
                                {{ $order['status_label'] }}
                            </text>
                        </row>
                    </row>
                    <text class="text-sm text-theme-on-surface-variant">
                        {{ $order['customer'] ? trim($order['customer']['firstname'].' '.$order['customer']['lastname']) : '—' }}
                    </text>
                    <row class="w-full justify-between">
                        <text class="text-xs text-theme-on-surface-variant">
                            {{ \Carbon\Carbon::parse($order['order_date'])->format('d/m/Y H:i') }}
                        </text>
                        <text class="text-sm font-semibold text-theme-on-surface">
                            {{ number_format($order['total_paid'], 2, ',', ' ') }} €
                        </text>
                    </row>
                </pressable>
            @empty
                @if (! $lastApiError)
                    <text ref="orders-empty" class="text-sm text-theme-on-surface-variant">
                        Aucune commande trouvée sur les {{ $this->windowLabel() }}.
                    </text>
                @endif
            @endforelse

            @if ($hasMorePages)
                <button ref="orders-load-more" variant="secondary" @press="loadMore">Charger plus</button>
            @endif
        </column>

        @include('native.partials.shop-switcher-sheet')
        @include('native.partials.account-sheet', [
            'accountInitials' => $this->accountInitials(),
            'accountName' => $this->accountName(),
            'accountEmail' => $this->accountEmail(),
        ])
    </column>
</refreshable>
