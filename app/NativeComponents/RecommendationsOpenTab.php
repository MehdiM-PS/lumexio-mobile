<?php

namespace App\NativeComponents;

use App\NativeComponents\Concerns\HandlesApiErrors;
use App\Services\LumexioApi;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;

class RecommendationsOpenTab extends NativeComponent
{
    use HandlesApiErrors;

    public string $priorityFilter = 'all';

    public array $recommendations = [];

    public array $stats = ['total' => 0, 'high_priority' => 0];

    public function mount(): void
    {
        $this->load();
    }

    /** Bound to pull-to-refresh; always bypasses the short-lived GET cache. */
    public function refresh(): void
    {
        $this->bustApiCache();
        $this->load();
    }

    private function load(): void
    {
        $this->resetApiError();

        $data = $this->callApi(fn () => app(LumexioApi::class)->get('/recommendations', [
            'priority' => $this->priorityFilter === 'all' ? null : $this->priorityFilter,
        ]));

        $this->recommendations = $data['recommendations'] ?? [];
        $this->stats = $data['stats'] ?? $this->stats;
    }

    /**
     * `$selected` is the chip's emitted on_change value, appended by the
     * dispatcher after the callback's own args. Each priority is its own
     * independent `<chip>` (no shared native:model group exists for
     * chips), so switching priorities fires this callback twice: once
     * with `selected: true` from the tapped chip, and once with
     * `selected: false` from the previously-active chip's own echo when
     * the re-render pushes its new, deselected value back down to it.
     * Without this guard, that `false` echo would blindly reassign
     * $priorityFilter back to that chip's own value, fighting the
     * just-made selection in an oscillating loop.
     */
    public function setPriorityFilter(string $priority, bool $selected = true): void
    {
        if (! $selected) {
            return;
        }

        $this->priorityFilter = $priority;
        $this->refresh();
    }

    public function markDone(int $id): void
    {
        $this->resetApiError();

        $result = $this->callApi(fn () => app(LumexioApi::class)->post('/recommendations/'.$id.'/done'));

        if ($result !== null) {
            $this->recommendations = array_values(array_filter($this->recommendations, fn ($r) => $r['id'] !== $id));
        }
    }

    public function reject(int $id): void
    {
        $this->resetApiError();

        $result = $this->callApi(fn () => app(LumexioApi::class)->post('/recommendations/'.$id.'/reject'));

        if ($result !== null) {
            $this->recommendations = array_values(array_filter($this->recommendations, fn ($r) => $r['id'] !== $id));
        }
    }

    public function render(): View
    {
        return view('native.recommendations-open-tab');
    }
}
