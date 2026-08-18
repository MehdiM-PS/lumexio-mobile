<column fill class="safe-area bg-theme-background justify-between px-6 py-8">
    <column class="gap-4">
        <row class="items-center gap-[10]">
            <native:image ref="login-logo" src="{{ $this->logoUrl() }}" :fit="1" alt="Lumexio" class="h-[34] w-[34] rounded-[10]" />
            <text class="text-xl font-bold text-theme-on-background">Lumexio</text>
        </row>

        <column class="gap-0 pt-6">
            <text class="text-[34] font-extrabold text-theme-on-background">Arrêtez de subir vos ventes.</text>
            <text class="text-[34] font-extrabold text-theme-primary">Anticipez-les.</text>
        </column>

        <text class="text-[15] text-theme-on-surface-variant">
            Prévisions de ventes, alertes de rupture et scoring clients par IA, directement sur votre boutique PrestaShop ou Shopify.
        </text>
    </column>

    <column class="gap-4">
        <column class="gap-[10]">
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
        </column>

        @if ($lastApiError)
            <text ref="login-error" class="text-sm text-theme-destructive">{{ $lastApiError }}</text>
        @endif

        <button ref="submit-button" variant="primary" :loading="$loading" @press="submit">
            Se connecter
        </button>

        <row class="justify-center gap-1">
            <text class="text-sm text-theme-on-surface-variant">Pas encore de compte ?</text>
            <pressable ref="signup-cta" @press="openSignup">
                <text class="text-sm font-semibold text-theme-primary">Essai gratuit 30 jours</text>
            </pressable>
        </row>

        <row class="justify-center">
            <text class="text-xs text-theme-on-surface-variant">🇫🇷 Hébergé en France · 🇪🇺 IA européenne</text>
        </row>
    </column>
</column>
