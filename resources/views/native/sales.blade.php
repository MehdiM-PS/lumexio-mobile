<refreshable @refresh="refresh">
    <column fill class="bg-theme-background items-center justify-center gap-2 p-4">
        @if ($lastApiError)
            <row ref="sales-error" class="w-full items-center gap-2 rounded-lg bg-theme-destructive/15 px-4 py-[10]">
                <text class="flex-1 text-sm text-theme-destructive">{{ $lastApiError }}</text>
            </row>
        @endif

        <text class="text-base font-semibold text-theme-on-background">Ventes</text>
        <text ref="sales-placeholder" class="text-sm text-theme-on-surface-variant">Analyse des ventes — bientôt disponible</text>

        @include('native.partials.shop-switcher-sheet')
        @include('native.partials.account-sheet', [
            'accountInitials' => $this->accountInitials(),
            'accountName' => $this->accountName(),
            'accountEmail' => $this->accountEmail(),
        ])
    </column>
</refreshable>
