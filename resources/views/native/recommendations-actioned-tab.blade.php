<refreshable fill @refresh="refresh">
    <column fill class="bg-theme-background gap-3 p-4">
        @if ($lastApiError)
            <row ref="reco-actioned-error" class="w-full items-center gap-2 rounded-lg bg-theme-destructive/15 px-4 py-[10]">
                <text class="flex-1 text-sm text-theme-destructive">{{ $lastApiError }}</text>
            </row>
        @endif

        <column class="w-full gap-2">
            @forelse ($recommendations as $rec)
                <column class="w-full gap-2 rounded-lg border border-theme-outline bg-theme-surface p-4">
                    <row class="w-full justify-between">
                        <text class="text-sm font-semibold text-theme-on-surface">{{ $rec['title'] }}</text>
                        @if ($rec['actioned_status'] === 'resolved')
                            <text ref="reco-{{ $rec['id'] }}-status-resolved" class="text-xs font-medium text-theme-primary">Résolu</text>
                        @elseif ($rec['actioned_status'] === 'monitoring')
                            <text ref="reco-{{ $rec['id'] }}-status-monitoring" class="text-xs font-medium text-theme-accent">Surveillé</text>
                        @endif
                    </row>
                    <text class="text-sm text-theme-on-surface-variant">{{ $rec['description'] }}</text>
                    <button ref="reco-{{ $rec['id'] }}-reopen" variant="secondary" @press="reopen({{ $rec['id'] }})">Réouvrir</button>
                </column>
            @empty
                @if (! $lastApiError)
                    <text ref="reco-actioned-empty" class="text-sm text-theme-on-surface-variant">Aucune recommandation traitée.</text>
                @endif
            @endforelse
        </column>
    </column>
</refreshable>
