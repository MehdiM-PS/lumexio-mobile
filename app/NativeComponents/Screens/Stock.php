<?php

namespace App\NativeComponents\Screens;

use Illuminate\View\View;
use Native\Mobile\Attributes\On;
use Native\Mobile\Edge\NativeComponent;

class Stock extends NativeComponent
{
    public int $activeTab = 0;

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
