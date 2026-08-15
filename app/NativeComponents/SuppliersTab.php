<?php

namespace App\NativeComponents;

use App\NativeComponents\Concerns\HandlesApiErrors;
use App\Services\LumexioApi;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;

class SuppliersTab extends NativeComponent
{
    use HandlesApiErrors;

    public string $search = '';

    public array $suppliers = [];

    public function mount(): void
    {
        $this->refresh();
    }

    public function refresh(): void
    {
        $this->resetApiError();

        $data = $this->callApi(fn () => app(LumexioApi::class)->get('/suppliers', [
            'search' => $this->search ?: null,
        ]));

        $this->suppliers = $data['suppliers']['data'] ?? [];
    }

    public function updatedSearch(): void
    {
        $this->refresh();
    }

    public function select(int $id): void
    {
        $supplier = collect($this->suppliers)->firstWhere('id', $id) ?? ['id' => $id];

        $this->navigate('/suppliers/'.$id, ['supplier' => $supplier]);
    }

    public function goToCreate(): void
    {
        $this->navigate('/suppliers/create');
    }

    public function render(): View
    {
        return view('native.suppliers-tab');
    }
}
