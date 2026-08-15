<refreshable @refresh="refresh">
    <column fill class="bg-theme-background gap-3 p-4">
        @if ($lastApiError)
            <row ref="reco-open-error" class="w-full items-center gap-2 rounded-lg bg-theme-destructive/15 px-4 py-[10]">
                <text class="flex-1 text-sm text-theme-destructive">{{ $lastApiError }}</text>
            </row>
        @endif

        <row class="w-full gap-3">
            <column class="flex-1 gap-1 rounded-lg bg-theme-surface-variant p-4">
                <text class="text-xs text-theme-on-surface-variant">Total</text>
                <text ref="reco-stats-total" class="text-xl font-bold text-theme-on-surface">{{ $stats['total'] }}</text>
            </column>
            <column class="flex-1 gap-1 rounded-lg bg-theme-surface-variant p-4">
                <text class="text-xs text-theme-on-surface-variant">Priorité haute</text>
                <text ref="reco-stats-high-priority" class="text-xl font-bold text-theme-destructive">{{ $stats['high_priority'] }}</text>
            </column>
        </row>

        <row class="w-full gap-2">
            <chip ref="reco-priority-all" label="Toutes" :selected="$priorityFilter === 'all'" @change="setPriorityFilter('all')" />
            <chip ref="reco-priority-high" label="Haute" :selected="$priorityFilter === 'high'" @change="setPriorityFilter('high')" />
            <chip ref="reco-priority-medium" label="Moyenne" :selected="$priorityFilter === 'medium'" @change="setPriorityFilter('medium')" />
            <chip ref="reco-priority-low" label="Basse" :selected="$priorityFilter === 'low'" @change="setPriorityFilter('low')" />
        </row>

        @forelse ($this->groupedRecommendations() as $groupKey => $items)
            <text ref="reco-group-{{ $groupKey }}-heading" class="text-base font-semibold text-theme-on-background">
                {{ $items->first()['group_label'] }}
            </text>
            <column class="w-full gap-2">
                @foreach ($items as $rec)
                    <column class="w-full gap-2 rounded-lg border-l-4 border-[{{ $rec['priority'] === 'high' ? '#ef4444' : ($rec['priority'] === 'medium' ? '#f59e0b' : '#60a5fa') }}] bg-theme-surface-variant p-4">
                        <row class="w-full justify-between">
                            <text class="text-sm font-semibold text-theme-on-surface">{{ $rec['title'] }}</text>
                            @if ($this->isNew($rec['created_at']))
                                <text ref="reco-{{ $rec['id'] }}-new-badge" class="text-xs font-medium text-theme-primary">NOUVEAU</text>
                            @endif
                        </row>
                        <text class="text-sm text-theme-on-surface-variant">{{ $rec['description'] }}</text>
                        @foreach ($rec['data_fields'] as $field)
                            <text class="text-xs text-theme-on-surface-variant">{{ $field['label'] }}: {{ $field['value'] }}</text>
                        @endforeach
                        <row class="w-full gap-2">
                            <button ref="reco-{{ $rec['id'] }}-done" variant="primary" @press="markDone({{ $rec['id'] }})">Fait</button>
                            <button ref="reco-{{ $rec['id'] }}-reject" variant="secondary" @press="reject({{ $rec['id'] }})">Rejeter</button>
                        </row>
                    </column>
                @endforeach
            </column>
        @empty
            @if (! $lastApiError)
                <text ref="reco-open-empty" class="text-sm text-theme-on-surface-variant">Aucune recommandation pour le moment.</text>
            @endif
        @endforelse
    </column>
</refreshable>
