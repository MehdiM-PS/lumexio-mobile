<native:bottom-sheet ref="account-sheet" :visible="$accountSheetOpen" detents="medium" @dismiss="closeAccountSheet">
    <column class="w-full gap-1 p-4">
        <row class="items-center gap-3 pb-[12]">
            <column class="h-[44] w-[44] items-center justify-center rounded-full bg-theme-surface-variant">
                <text class="text-sm font-bold text-theme-primary">{{ $accountInitials }}</text>
            </column>
            <column class="gap-0">
                <text ref="account-sheet-name" class="text-sm font-bold text-theme-on-surface">{{ $accountName }}</text>
                <text ref="account-sheet-email" class="text-xs text-theme-on-surface-variant">{{ $accountEmail }}</text>
            </column>
        </row>

        <pressable ref="account-sheet-profile" class="w-full rounded-lg px-2 py-[12]" @press="goProfile">
            <text class="text-sm font-medium text-theme-on-surface">Mon profil</text>
        </pressable>
        <pressable class="w-full rounded-lg px-2 py-[12]" @press="openShopSwitcher">
            <text class="text-sm font-medium text-theme-on-surface">Gérer mes boutiques</text>
        </pressable>
        <pressable ref="account-sheet-logout" class="w-full rounded-lg px-2 py-[12]" @press="logout">
            <text class="text-sm font-medium text-theme-destructive">Déconnexion</text>
        </pressable>
    </column>
</native:bottom-sheet>
