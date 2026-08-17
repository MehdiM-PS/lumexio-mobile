<?php

namespace App\NativeComponents\Screens;

use App\NativeComponents\Concerns\HandlesApiErrors;
use App\NativeComponents\Concerns\HasHeaderChrome;
use Illuminate\View\View;
use Native\Mobile\Attributes\On;
use Native\Mobile\Edge\NativeComponent;

class Stock extends NativeComponent
{
    use HandlesApiErrors;
    use HasHeaderChrome;

    public int $activeTab = 0;

    /**
     * Stock has no data of its own — its three inner tabs (products, alerts,
     * suppliers) each fetch independently. mount() exists solely to load the
     * shared header's account data, matching the HasHeaderChrome contract
     * (see Dashboard::refresh() / Orders::refresh()).
     */
    public function mount(): void
    {
        $this->resetApiError();
        $this->loadCurrentUser();
    }

    #[On('select-item')]
    public function onSelectItem(string $type, int $id, ?array $item = null): void
    {
        $this->navigate('/stock/item/'.$type.'/'.$id, ['item' => $item ?? ['type' => $type, 'id' => $id]]);
    }

    public function render(): View
    {
        return view('native.stock');
    }
}
