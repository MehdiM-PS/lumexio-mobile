<top-bar title="Mon profil" back />

<scroll-view fill class="bg-theme-background">
    <column class="w-full bg-theme-background gap-4 p-4">
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

        <text class="text-sm font-bold text-theme-on-surface pt-2">Changer le mot de passe</text>
        <outlined-text-input ref="profile-current-password-input" native:model="currentPasswordInput" label="Mot de passe actuel" secure :disabled="$savingPassword" />
        <outlined-text-input ref="profile-new-password-input" native:model="newPasswordInput" label="Nouveau mot de passe" secure :disabled="$savingPassword" />
        <outlined-text-input ref="profile-new-password-confirmation-input" native:model="newPasswordConfirmationInput" label="Confirmer le nouveau mot de passe" secure :disabled="$savingPassword" />
        <button ref="profile-change-password-button" variant="primary" :loading="$savingPassword" @press="changePassword">Mettre à jour le mot de passe</button>
    </column>
</scroll-view>
