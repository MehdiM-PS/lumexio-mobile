<top-bar title="Mon compte" back />

<column fill class="bg-theme-background gap-4 p-4">
    @if ($lastApiError)
        <row ref="account-error" class="w-full items-center gap-2 rounded-lg bg-theme-destructive/15 px-4 py-[10]">
            <text class="flex-1 text-sm text-theme-destructive">{{ $lastApiError }}</text>
        </row>
    @endif

    <text class="text-sm font-bold text-theme-on-surface">Changer le mot de passe</text>
    <outlined-text-input ref="account-current-password-input" native:model="currentPasswordInput" label="Mot de passe actuel" secure :disabled="$saving" />
    <outlined-text-input ref="account-new-password-input" native:model="newPasswordInput" label="Nouveau mot de passe" secure :disabled="$saving" />
    <outlined-text-input ref="account-new-password-confirmation-input" native:model="newPasswordConfirmationInput" label="Confirmer le nouveau mot de passe" secure :disabled="$saving" />
    <button ref="account-change-password-button" variant="primary" :loading="$saving" @press="changePassword">Mettre à jour le mot de passe</button>

    <row class="w-full justify-between pt-4">
        <text class="text-xs text-theme-on-surface-variant">Version de l'app</text>
        <text ref="account-app-version" class="text-xs text-theme-on-surface-variant">{{ $this->appVersion() }}</text>
    </row>
</column>
