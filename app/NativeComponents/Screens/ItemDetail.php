<?php

namespace App\NativeComponents\Screens;

use App\NativeComponents\Concerns\HandlesApiErrors;
use App\Services\LumexioApi;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;

class ItemDetail extends NativeComponent
{
    use HandlesApiErrors;

    public array $item = [];

    public array $historyLabels = [];

    public array $historyQuantities = [];

    public string $thresholdInput = '';

    public string $leadTimeInput = '';

    public function mount(): void
    {
        $this->item = $this->data('item', [
            'type' => $this->param('type'),
            'id' => (int) $this->param('id'),
            'name' => '',
            'reference' => null,
            'quantity' => 0,
            'low_stock_threshold' => 0,
            'supplier_lead_time_days' => 0,
        ]);

        $this->thresholdInput = (string) ($this->item['low_stock_threshold'] ?? 0);
        $this->leadTimeInput = (string) ($this->item['supplier_lead_time_days'] ?? 0);

        $this->load();
    }

    /** Bound to pull-to-refresh; always bypasses the short-lived GET cache. */
    public function loadHistory(): void
    {
        $this->bustApiCache();
        $this->load();
    }

    private function load(): void
    {
        $this->resetApiError();

        $data = $this->callApi(fn () => app(LumexioApi::class)->get('/products/stock-history', [
            'type' => $this->item['type'],
            'id' => $this->item['id'],
        ]));

        $history = array_reverse($data['history'] ?? []);
        $this->historyLabels = array_column($history, 'date_formatted');
        $this->historyQuantities = array_map(fn ($h) => $h['quantity'] ?? 0, $history);
    }

    public function saveThreshold(): void
    {
        $this->resetApiError();

        $data = $this->callApi(fn () => app(LumexioApi::class)->patch(
            '/products/'.$this->item['id'].'/threshold',
            ['threshold' => (int) $this->thresholdInput],
        ));

        if ($data !== null) {
            $this->item['low_stock_threshold'] = $data['product']['low_stock_threshold'];
        } else {
            $this->thresholdInput = (string) $this->item['low_stock_threshold'];
        }
    }

    public function saveLeadTime(): void
    {
        $this->resetApiError();

        $data = $this->callApi(fn () => app(LumexioApi::class)->patch(
            '/products/'.$this->item['id'].'/lead-time',
            ['supplier_lead_time_days' => (int) $this->leadTimeInput],
        ));

        if ($data !== null) {
            $this->item['supplier_lead_time_days'] = $data['product']['supplier_lead_time_days'];
        } else {
            $this->leadTimeInput = (string) $this->item['supplier_lead_time_days'];
        }
    }

    /**
     * The single stock-level series the history chart draws. Unnamed on
     * purpose: with one series there is nothing to tell apart, so the chart
     * skips the legend and the tooltip shows just the quantity.
     *
     * @return list<array{data: list<int>}>
     */
    public function historySeries(): array
    {
        return [['data' => $this->historyQuantities]];
    }

    public function render(): View
    {
        return view('native.item-detail');
    }
}
