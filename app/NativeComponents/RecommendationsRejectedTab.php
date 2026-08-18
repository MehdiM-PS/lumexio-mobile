<?php

namespace App\NativeComponents;

use App\NativeComponents\Concerns\HandlesApiErrors;
use App\Services\LumexioApi;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;

class RecommendationsRejectedTab extends NativeComponent
{
    use HandlesApiErrors;

    public array $recommendations = [];

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

        $data = $this->callApi(fn () => app(LumexioApi::class)->get('/recommendations/rejected'));

        $this->recommendations = $data['recommendations'] ?? [];
    }

    public function unreject(int $id): void
    {
        $this->resetApiError();

        $result = $this->callApi(fn () => app(LumexioApi::class)->post('/recommendations/'.$id.'/unreject'));

        if ($result !== null) {
            $this->recommendations = array_values(array_filter($this->recommendations, fn ($r) => $r['id'] !== $id));
        }
    }

    public function render(): View
    {
        return view('native.recommendations-rejected-tab');
    }
}
