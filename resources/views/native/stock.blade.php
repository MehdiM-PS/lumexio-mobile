<column fill class="bg-theme-background">
    <tab-row native:model="activeTab" class="w-full">
        <tab label="Produits" />
        <tab label="Alertes" />
    </tab-row>

    {{-- native: prefix is required here, unlike core elements (tab-row/tab above): the Blade
    precompiler's bare-tag allowlist is built only from ElementRegistry (core), never from
    ComponentRegistry (app/NativeComponents/* child components) — a bare tag silently renders
    as literal markup instead of mounting the component. See NativeServiceProvider.php's
    $shortFormTags construction. --}}
    @if ($activeTab === 0)
        <native:stock-products-tab />
    @else
        <native:stock-alerts-tab />
    @endif
</column>
