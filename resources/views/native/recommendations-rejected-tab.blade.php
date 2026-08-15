<refreshable @refresh="refresh">
    <column fill class="bg-theme-background gap-3 p-4">
        @if ($lastApiError)
            <row ref="reco-rejected-error" class="w-full items-center gap-2 rounded-lg bg-theme-destructive/15 px-4 py-[10]">
                <text class="flex-1 text-sm text-theme-destructive">{{ $lastApiError }}</text>
            </row>
        @endif

        <column class="w-full gap-2">
            @forelse ($recommendations as $rec)
                <column class="w-full gap-2 rounded-lg bg-theme-surface-variant p-4">
                    <text ref="reco-{{ $rec['id'] }}-title" class="text-sm font-semibold text-theme-on-surface">{{ $rec['title'] }}</text>
                    <text class="text-sm text-theme-on-surface-variant">{{ $rec['description'] }}</text>
                    <button ref="reco-{{ $rec['id'] }}-unreject" variant="secondary" @press="unreject({{ $rec['id'] }})">Restaurer</button>
                </column>
            @empty
                @if (! $lastApiError)
                    <text ref="reco-rejected-empty" class="text-sm text-theme-on-surface-variant">Aucune recommandation rejetée.</text>
                @endif
            @endforelse
        </column>
    </column>
</refreshable>
