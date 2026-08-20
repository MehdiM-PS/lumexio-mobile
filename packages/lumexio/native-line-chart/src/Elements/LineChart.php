<?php

namespace Lumexio\NativeLineChart\Elements;

use Native\Mobile\Edge\CallbackRegistry;
use Native\Mobile\Edge\Element;

/**
 * A smooth line/area chart rendered natively (SwiftUI Canvas / Compose
 * Canvas) from a pair of null-padded series — mirrors Chart.js's
 * two-dataset-with-nulls technique so the two lines join at a shared index
 * instead of rendering as disconnected segments.
 */
class LineChart extends Element
{
    protected string $type = 'lumexio_line_chart';

    protected array $componentProps = [];

    public static function make(): static
    {
        return new static;
    }

    /**
     * @param  array{historical: list<float|null>, forecast: list<float|null>, dates?: list<string>}  $series
     */
    public function data(array $series): static
    {
        $this->componentProps['data'] = json_encode($series);

        return $this;
    }

    public function historicalColor(string $hex): static
    {
        $this->componentProps['historical_color'] = $hex;

        return $this;
    }

    public function forecastColor(string $hex): static
    {
        $this->componentProps['forecast_color'] = $hex;

        return $this;
    }

    public function selectedIndex(?int $index): static
    {
        if ($index !== null) {
            $this->componentProps['selected_index'] = $index;
        }

        return $this;
    }

    public function onChange(string $method): static
    {
        $this->componentProps['on_change'] = $method;

        return $this;
    }

    public function applyAttributes(array $attrs): void
    {
        if (isset($attrs['data']) && is_array($attrs['data'])) {
            $this->data($attrs['data']);
        }

        if (isset($attrs['historical-color'])) {
            $this->historicalColor($attrs['historical-color']);
        }

        if (isset($attrs['forecast-color'])) {
            $this->forecastColor($attrs['forecast-color']);
        }

        if (isset($attrs['selected-index'])) {
            $this->selectedIndex((int) $attrs['selected-index']);
        }

        if (isset($attrs['_change'])) {
            $this->onChange($attrs['_change']);
        }
    }

    protected function resolveProps(CallbackRegistry $registry): array
    {
        $props = $this->componentProps;

        if (isset($props['on_change'])) {
            $props['on_change'] = $registry->register($props['on_change']);
        }

        return $props;
    }
}
