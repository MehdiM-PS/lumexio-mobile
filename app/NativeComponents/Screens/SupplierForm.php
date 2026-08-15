<?php

namespace App\NativeComponents\Screens;

use App\Models\LocalState;
use App\NativeComponents\Concerns\HandlesApiErrors;
use App\Services\LumexioApi;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;

class SupplierForm extends NativeComponent
{
    use HandlesApiErrors;

    public ?int $supplierId = null;

    public string $name = '';

    public string $contactName = '';

    public string $email = '';

    public string $phone = '';

    public string $address = '';

    public string $notes = '';

    public bool $loading = false;

    public function mount(): void
    {
        // Two distinct routes map here: '/suppliers/create' (no {id} segment,
        // so param('id') is null) and '/suppliers/{id}' (edit).
        $idParam = $this->param('id');
        $this->supplierId = $idParam !== null ? (int) $idParam : null;

        $supplier = $this->data('supplier');
        if ($supplier !== null) {
            $this->name = $supplier['name'] ?? '';
            $this->contactName = $supplier['contact_name'] ?? '';
            $this->email = $supplier['email'] ?? '';
            $this->phone = $supplier['phone'] ?? '';
            $this->address = $supplier['address'] ?? '';
            $this->notes = $supplier['notes'] ?? '';
        }
    }

    public function submit(): void
    {
        $this->loading = true;
        $this->resetApiError();

        $body = [
            'name' => $this->name,
            'contact_name' => $this->contactName ?: null,
            'email' => $this->email ?: null,
            'phone' => $this->phone ?: null,
            'address' => $this->address ?: null,
            'notes' => $this->notes ?: null,
        ];

        if ($this->supplierId === null) {
            $body['shop_id'] = LocalState::current()->shop_id;
            $result = $this->callApi(fn () => app(LumexioApi::class)->post('/suppliers', $body));
        } else {
            $result = $this->callApi(fn () => app(LumexioApi::class)->patch('/suppliers/'.$this->supplierId, $body));
        }

        $this->loading = false;

        if ($result !== null) {
            $this->back();
        }
    }

    public function render(): View
    {
        return view('native.supplier-form');
    }
}
