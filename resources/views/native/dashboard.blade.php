<refreshable @refresh="refresh">
    <column fill class="bg-theme-background px-4 py-4 gap-4">
        {{-- native:model comes before @change so its expanded `_change` attr
        (silent __syncProperty write) is overwritten by @change's `_change`
        (setDayScope callback) rather than the other way around — PHP array
        literals resolve duplicate keys last-wins. native:model still drives
        the read side (:value="$dayScope"); @change carries the write side
        since setDayScope() has a refresh() side effect, unlike tab-row's
        bare native:model in stock.blade.php. --}}
        <button-group ref="dashboard-day-scope" native:model="dayScope" @change="setDayScope"
                      :options="['Hier', 'Aujourd\'hui']" class="w-full"/>

        @if ($syncStatus === 'failed')
            <row ref="dashboard-sync-failed"
                 class="w-full items-center gap-2 rounded-lg bg-theme-destructive/15 px-4 py-[10]">
                <text class="flex-1 text-sm text-theme-destructive">
                    {{ filled($syncError) ? $syncError : 'La dernière synchronisation a échoué. Les données peuvent être obsolètes.' }}
                </text>
            </row>
        @elseif ($syncStatus === 'syncing')
            <row ref="dashboard-sync-syncing"
                 class="w-full items-center gap-2 rounded-lg bg-theme-surface-variant px-4 py-[10]">
                <text class="flex-1 text-sm text-theme-on-surface-variant">Synchronisation en cours…</text>
            </row>
        @endif

        @if ($lastApiError)
            <row ref="dashboard-error"
                 class="w-full items-center gap-2 rounded-lg bg-theme-destructive/15 px-4 py-[10]">
                <text class="flex-1 text-sm text-theme-destructive">{{ $lastApiError }}</text>
            </row>
        @endif

        @php $heroDelta = $this->heroDeltaPercent(); @endphp
        <row class="w-full justify-between items-start">
            <column class="gap-1">
                <text ref="dashboard-hero-day-label" class="text-xs text-theme-on-surface-variant">
                    CA {{ $dayScope === 1 ? "aujourd'hui" : "d'hier" }}</text>
                <text ref="dashboard-hero-revenue" class="text-5xl font-bold text-theme-on-background"
                      content-transition="numeric">
                    {{ number_format(abs($metrics['revenue_today']) ?? 0, 0, ',', ' ') }} €
                </text>
            </column>
        </row>
        @if ($widgets['ca_forecast_percent'] !== null)
            <column class="w-full gap-1">
                <progress-bar value="{{ abs($widgets['ca_forecast_percent']) / 100 }}" class="w-full"/>
                <row class="w-full justify-between items-center">
                    {{--<text ref="dashboard-hero-delta"
                          class="text-sm font-semibold {{ $heroDelta >= 0 ? 'text-theme-success' : 'text-theme-destructive' }}">
                        {{ $heroDelta >= 0 ? '↑' : '↓' }} {{ number_format(abs($heroDelta), 0, ',', ' ') }}%
                    </text>--}}
                    <text ref="dashboard-hero-delta"
                          class="text-sm font-semibold {{ $widgets['ca_forecast_percent'] >= 100 ? 'text-theme-success' : 'text-theme-destructive' }}">
                        {{ number_format(abs($widgets['ca_forecast_percent']), 0, ',', ' ') }}% atteint
                    </text>
                    <text ref="dashboard-hero-revenue-forecast"
                          class="text-sm font-semibold text-theme-on-surface-variant">
                        sur {{ number_format(abs($widgets['ca_forecast_predicted']) ?? 0, 0, ',', ' ') }} € prévu
                    </text>
                </row>
            </column>
        @endif

        {{-- Mock's kpis array spans "Prévision 30j" across both grid columns
        (`grid-column: span 2`) so it's alone on row 1, with "Ruptures
        prévues" and "Clients VIP" sharing row 2 — not the 2-up/1-alone
        split this used to render. --}}
        <row ref="dashboard-kpi-row-forecast" class="w-full">
            <column class="flex-1 gap-1 rounded-lg bg-theme-surface border border-theme-outline p-4">
                <text class="text-xs text-theme-on-surface-variant">Prévision 30j</text>
                <text ref="dashboard-kpi-forecast-30d" class="text-xl font-bold text-theme-on-surface"
                      content-transition="numeric">
                    {{ number_format($this->forecast30d(), 0, ',', ' ') }} €
                </text>
                <text class="text-xs text-theme-on-surface-variant">Confiance {{ $this->forecastConfidence() }}%</text>
            </column>
        </row>

        <row ref="dashboard-kpi-row-secondary" class="w-full gap-3">
            <column class="flex-1 gap-1 rounded-lg bg-theme-surface border border-theme-outline p-4">
                <text class="text-xs text-theme-on-surface-variant">Ruptures prévues</text>
                <text ref="dashboard-kpi-critical-stock" class="text-xl font-bold text-theme-on-surface"
                      content-transition="numeric">
                    {{ $this->criticalStockCount() }}
                </text>
                <text class="text-xs text-theme-accent">Sous 7 jours</text>
            </column>
            <column class="flex-1 gap-1 rounded-lg bg-theme-surface border border-theme-outline p-4">
                <text class="text-xs text-theme-on-surface-variant">Clients VIP</text>
                <text ref="dashboard-kpi-vip" class="text-xl font-bold text-theme-on-surface"
                      content-transition="numeric">
                    {{ $this->vipCount() }}
                </text>
                <text ref="dashboard-kpi-at-risk" class="text-xs text-theme-destructive">{{ $this->atRiskCount() }} à
                    risque
                </text>
            </column>
        </row>

        <row class="w-full justify-between items-baseline">
            <text class="text-base font-semibold text-theme-on-background">Alertes récentes</text>
            <pressable ref="dashboard-alerts-see-all" class="px-[4] py-[6]" @press="goAlerts">
                <text class="text-sm font-semibold text-theme-primary">Tout voir</text>
            </pressable>
        </row>
        <column class="w-full gap-2">
            @forelse ($recentAlerts as $alert)
                <row
                    class="w-full items-start gap-2 rounded-lg bg-theme-surface border border-theme-outline px-4 py-[10]">
                    <column
                        class="h-[8] w-[8] rounded-full {{ ($alert['severity'] ?? 'info') === 'critical' ? 'bg-theme-destructive' : (($alert['severity'] ?? 'info') === 'warning' ? 'bg-theme-accent' : 'bg-theme-primary') }} mt-[6]"/>
                    <column class="flex-1 gap-0">
                        <text class="text-sm font-semibold text-theme-on-surface">{{ $alert['title'] ?? '' }}</text>
                        <text class="text-xs text-theme-on-surface-variant">{{ $alert['message'] ?? '' }}</text>
                        @if (! empty($alert['created_at']))
                            <text
                                class="text-xs text-theme-on-surface-variant">{{ \Carbon\Carbon::parse($alert['created_at'])->format('d/m/Y H:i') }}</text>
                        @endif
                    </column>
                </row>
            @empty
                <text ref="dashboard-alerts-empty" class="text-sm text-theme-on-surface-variant">Aucune alerte
                    récente.
                </text>
            @endforelse
        </column>

        {{--
            accountInitials/accountName/accountEmail are passed explicitly
            rather than called from inside account-sheet.blade.php:
            NativeComponent::renderBladeBoundToSelf() only binds `$this` to
            the component for the top-level compiled view — Blade's own
            @include mechanism (PhpEngine::evaluatePath ->
            Filesystem::getRequire) runs the nested view through a static
            closure with no object context, so `$this->...()` inside an
            @include'd partial fails with "Using $this when not in object
            context". Every screen that includes this partial (Tasks 7-9)
            must pass these same three keys.
            shop-switcher-sheet.blade.php has no such requirement: it reads
            LocalState::current()->shop_id directly (a static call, no $this
            needed) instead of $this->currentShopId(), and $shopSheetOpen /
            $switcherShops are plain public properties already in scope —
            so it can be @include'd with no extra data, as the brief intended.
        --}}
        @include('native.partials.shop-switcher-sheet')
        @include('native.partials.account-sheet', [
            'accountInitials' => $this->accountInitials(),
            'accountName' => $this->accountName(),
            'accountEmail' => $this->accountEmail(),
        ])
    </column>
</refreshable>
