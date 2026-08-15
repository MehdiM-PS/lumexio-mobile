<?php

namespace App\NativeComponents\Screens;

use App\NativeComponents\Concerns\HandlesApiErrors;
use App\Services\LumexioApi;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;

class OrderDetail extends NativeComponent
{
    use HandlesApiErrors;

    public array $order = [];

    public function mount(): void
    {
        $this->order = $this->data('order', [
            'id' => (int) $this->param('id'),
            'reference' => '',
        ]);

        $this->loadDetail();
    }

    public function loadDetail(): void
    {
        $this->resetApiError();

        $data = $this->callApi(fn () => app(LumexioApi::class)->get('/orders/'.$this->order['id']));

        if ($data !== null) {
            $this->order = $data['order'];
        }
    }

    public function render(): View
    {
        return view('native.order-detail');
    }
}
