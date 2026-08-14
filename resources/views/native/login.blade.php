<column fill center class="safe-area bg-theme-background px-6 gap-4">
    <text class="text-2xl font-bold text-center text-theme-on-background" font="InstrumentSerif-Regular">
        Lumexio
    </text>

    <outlined-text-input
        ref="email-input"
        native:model="email"
        label="Email"
        keyboard="email"
        :disabled="$loading"
    />

    <outlined-text-input
        ref="password-input"
        native:model="password"
        label="Mot de passe"
        secure
        keyboard="password"
        :disabled="$loading"
    />

    @if ($lastApiError)
        <text ref="login-error" class="text-sm text-theme-destructive">{{ $lastApiError }}</text>
    @endif

    <button ref="submit-button" variant="primary" :loading="$loading" @press="submit">
        Se connecter
    </button>
</column>
