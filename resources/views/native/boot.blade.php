<column fill center class="safe-area bg-theme-background px-6 gap-4">
    @if ($lastApiError)
        <text ref="boot-error" class="text-sm text-theme-destructive text-center">{{ $lastApiError }}</text>
        <button ref="boot-retry" variant="primary" @press="retry">Réessayer</button>
    @else
        <activity-indicator size="lg" />
    @endif
</column>
