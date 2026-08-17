<refreshable fill @refresh="refresh">
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

        <column class="w-full gap-2">
            @forelse ($recommendations as $rec)
                <column ref="reco-{{ $rec['id'] }}-card" class="w-full gap-2 rounded-lg border border-theme-outline bg-theme-surface p-4">
                    <row class="w-full items-center gap-2">
                        <badge
                            ref="reco-{{ $rec['id'] }}-priority-badge"
                            label="{{ $rec['priority_label'] }}"
                            variant="{{ $rec['priority'] === 'high' ? 'destructive' : ($rec['priority'] === 'medium' ? 'accent' : 'primary') }}"
                        />
                        <text class="flex-1 text-xs text-theme-on-surface-variant">{{ $rec['type_label'] }} · {{ $rec['freshness_label'] }}</text>
                    </row>

                    <text class="text-sm font-semibold text-theme-on-surface">{{ $rec['title'] }}</text>

                    @if ($rec['ref'])
                        <text ref="reco-{{ $rec['id'] }}-ref" class="text-xs text-theme-on-surface-variant">{{ $rec['ref'] }}</text>
                    @endif

                    <text class="text-sm text-theme-on-surface-variant">{{ $rec['description'] }}</text>

                    @if (! empty($rec['data_fields']))
                        <column ref="reco-{{ $rec['id'] }}-metrics" class="w-full gap-2">
                            @foreach (array_chunk($rec['data_fields'], 2) as $pair)
                                <row class="w-full gap-3">
                                    @foreach ($pair as $field)
                                        <column class="flex-1 gap-1">
                                            <text class="text-xs text-theme-on-surface-variant">{{ $field['label'] }}</text>
                                            <text class="text-sm font-semibold text-theme-on-surface">{{ $field['value'] }}</text>
                                        </column>
                                    @endforeach
                                </row>
                            @endforeach
                        </column>
                    @endif

                    @if (! empty($rec['actions']))
                        <column class="w-full gap-1">
                            <text class="text-xs font-semibold text-theme-on-surface-variant">Actions recommandées</text>
                            @foreach ($rec['actions'] as $action)
                                <text class="text-xs text-theme-on-surface-variant">• {{ $action }}</text>
                            @endforeach
                        </column>
                    @endif

                    <row class="w-full gap-2">
                        <button ref="reco-{{ $rec['id'] }}-done" variant="primary" icon="checkmark" @press="markDone({{ $rec['id'] }})">Effectuée</button>
                        <button ref="reco-{{ $rec['id'] }}-reject" variant="secondary" icon="xmark" @press="reject({{ $rec['id'] }})">Pas intéressé</button>
                    </row>
                </column>
            @empty
                @if (! $lastApiError)
                    <text ref="reco-open-empty" class="text-sm text-theme-on-surface-variant">Aucune recommandation pour le moment.</text>
                @endif
            @endforelse
        </column>
    </column>
</refreshable>
