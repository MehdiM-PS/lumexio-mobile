<refreshable fill @refresh="refresh">
    <column fill class="bg-theme-background gap-3 p-4">
        @if ($lastApiError)
            <row ref="suppliers-error" class="w-full items-center gap-2 rounded-lg bg-theme-destructive/15 px-4 py-[10]">
                <text class="flex-1 text-sm text-theme-destructive">{{ $lastApiError }}</text>
            </row>
        @endif

        <row class="w-full gap-2 items-end">
            <column class="flex-1">
                <outlined-text-input ref="suppliers-search" native:model.debounce.400ms="search" label="Rechercher" placeholder="Nom, contact ou email" />
            </column>
            <button ref="suppliers-add" variant="primary" a11y-label="Ajouter un fournisseur" @press="goToCreate">+</button>
        </row>

        <column class="w-full gap-2">
            @forelse ($suppliers as $supplier)
                <pressable
                    ref="supplier-{{ $supplier['id'] }}"
                    class="w-full items-start gap-1 rounded-lg border border-theme-outline bg-theme-surface px-4 py-[12]"
                    @press="select({{ $supplier['id'] }})"
                >
                    <row class="w-full justify-between">
                        <text ref="supplier-{{ $supplier['id'] }}-name" class="text-sm font-semibold text-theme-on-surface">{{ $supplier['name'] }}</text>
                        @if ($supplier['active_orders_count'] > 0)
                            <badge label="{{ $supplier['active_orders_count'] }} en cours" variant="primary" />
                        @endif
                    </row>
                    @if ($supplier['contact_name'])
                        <text class="text-sm text-theme-on-surface-variant">{{ $supplier['contact_name'] }}</text>
                    @endif
                    @if ($supplier['email'])
                        <text class="text-xs text-theme-on-surface-variant">{{ $supplier['email'] }}</text>
                    @endif
                    @if ($supplier['phone'])
                        <text class="text-xs text-theme-on-surface-variant">{{ $supplier['phone'] }}</text>
                    @endif
                </pressable>
            @empty
                @if (! $lastApiError)
                    <text ref="suppliers-empty" class="text-sm text-theme-on-surface-variant">Aucun fournisseur trouvé.</text>
                @endif
            @endforelse
        </column>
    </column>
</refreshable>
