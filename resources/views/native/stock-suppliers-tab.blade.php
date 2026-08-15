<column fill class="bg-theme-background">
    <tab-row ref="stock-suppliers-subtabs" native:model="activeSubTab" class="w-full">
        <tab label="Commandes" />
        <tab label="Annuaire" />
    </tab-row>

    {{-- native: prefix required — see stock.blade.php's own comment for the full rationale
    (ComponentRegistry-registered child tags, unlike core ElementRegistry elements like
    tab-row/tab above, need the native: prefix or they silently render as literal markup). --}}
    @if ($activeSubTab === 0)
        <native:supplier-orders-tab />
    @else
        <native:suppliers-tab />
    @endif
</column>
