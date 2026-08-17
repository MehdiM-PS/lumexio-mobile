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
        $this->refresh();
    }

    public function refresh(): void
    {
        $this->resetApiError();

        $data = $this->callApi(fn () => app(LumexioApi::class)->get('/recommendations', [
            'priority' => $this->priorityFilter === 'all' ? null : $this->priorityFilter,
        ]));

        $this->recommendations = $data['recommendations'] ?? [];
        $this->stats = $data['stats'] ?? $this->stats;
    }

    public function setPriorityFilter(string $priority): void
    {
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
