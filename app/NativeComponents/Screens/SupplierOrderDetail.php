<?php

namespace App\NativeComponents\Screens;

use App\NativeComponents\Concerns\HandlesApiErrors;
use App\Services\LumexioApi;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;

class SupplierOrderDetail extends NativeComponent
{
    use HandlesApiErrors;

    public array $order = [];

    public const PENDING_STATUSES = ['commandee', 'en_cours', 'recue_partielle'];

    public function mount(): void
    {
        $this->order = $this->data('order', [
            'id' => (string) $this->param('id'),
            'reference' => '',
        ]);

        $this->loadDetail();
    }

    public function loadDetail(): void
    {
        $this->resetApiError();

        $data = $this->callApi(fn () => app(LumexioApi::class)->get('/supplier-orders/'.$this->order['id']));

        if ($data !== null) {
            $this->order = $data['order'];
        }
    }

    public function transitionTo(string $status): void
    {
        $this->resetApiError();

        $result = $this->callApi(fn () => app(LumexioApi::class)->post('/supplier-orders/'.$this->order['id'].'/transition', [
            'status' => $status,
        ]));

        if ($result !== null) {
            $this->loadDetail();
        }
    }

    public function markFullyReceived(): void
    {
        $this->resetApiError();

        $receivedQuantities = collect($this->order['items'] ?? [])
            ->mapWithKeys(fn ($item) => [$item['id'] => $item['quantity']])
            ->all();

        $result = $this->callApi(fn () => app(LumexioApi::class)->post('/supplier-orders/'.$this->order['id'].'/receive', [
            'received_quantities' => $receivedQuantities,
        ]));

        if ($result !== null) {
            $this->loadDetail();
        }
    }

    public function duplicate(): void
    {
        $this->resetApiError();

        $result = $this->callApi(fn () => app(LumexioApi::class)->post('/supplier-orders/'.$this->order['id'].'/duplicate'));

        if ($result !== null) {
            $this->navigate('/supplier-orders/'.$result['order']['id'], ['order' => $result['order']]);
        }
    }

    public function render(): View
    {
        return view('native.supplier-order-detail');
    }
}
