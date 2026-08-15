<?php

namespace App\NativeComponents;

use App\NativeComponents\Concerns\HandlesApiErrors;
use App\Services\LumexioApi;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;

class RecommendationsActionedTab extends NativeComponent
{
    use HandlesApiErrors;

    public array $recommendations = [];

    public function mount(): void
    {
        $this->refresh();
    }

    public function refresh(): void
    {
        $this->resetApiError();

        $data = $this->callApi(fn () => app(LumexioApi::class)->get('/recommendations/actioned'));

        $this->recommendations = $data['recommendations'] ?? [];
    }

    public function reopen(int $id): void
    {
        $this->resetApiError();

        $result = $this->callApi(fn () => app(LumexioApi::class)->post('/recommendations/'.$id.'/reopen'));

        if ($result !== null) {
            $this->recommendations = array_values(array_filter($this->recommendations, fn ($r) => $r['id'] !== $id));
        }
    }

    public function render(): View
    {
        return view('native.recommendations-actioned-tab');
    }
}
