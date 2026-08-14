<column fill class="bg-theme-background">
    <tab-row native:model="activeTab" class="w-full">
        <tab label="Produits" />
        <tab label="Alertes" />
    </tab-row>

    @if ($activeTab === 0)
        <native:stock-products-tab />
    @else
        <native:stock-alerts-tab />
    @endif
</column>
