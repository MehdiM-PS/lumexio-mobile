<top-bar title="Mon profil" back />

<column fill class="bg-theme-background gap-4 p-4">
    @if ($lastApiError)
        <row ref="profile-error" class="w-full items-center gap-2 rounded-lg bg-theme-destructive/15 px-4 py-[10]">
            <text class="flex-1 text-sm text-theme-destructive">{{ $lastApiError }}</text>
        </row>
    @endif

    <column class="w-full items-center gap-2 py-4">
        <column class="h-[64] w-[64] items-center justify-center rounded-full bg-theme-surface-variant">
            <text class="text-lg font-bold text-theme-primary">{{ $this->accountInitialsFor($nameInput) }}</text>
        </column>
    </column>

    <outlined-text-input ref="profile-name-input" native:model="nameInput" label="Nom" :disabled="$saving" />
    <outlined-text-input ref="profile-email-input" native:model="emailInput" label="Email" keyboard="email" :disabled="$saving" />

    <button ref="profile-save-button" variant="primary" :loading="$saving" @press="save">Enregistrer</button>
</column>
