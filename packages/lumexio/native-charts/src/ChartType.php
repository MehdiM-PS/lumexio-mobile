<?php

namespace Lumexio\NativeCharts;

/**
 * The chart shapes the renderer knows how to draw.
 *
 * `Bar` renders one group of bars per x label — a multi-series `Bar` is a
 * grouped bar chart, so grouping needs no separate case. `StackedBar` is the
 * same geometry with the series summed into one column per label.
 */
enum ChartType: string
{
    case Line = 'line';
    case Area = 'area';
    case Bar = 'bar';
    case StackedBar = 'stacked-bar';
    case HorizontalBar = 'horizontal-bar';
    case Pie = 'pie';
    case Donut = 'donut';

    /** Cartesian types have x/y axes, gridlines and an index-based crosshair. */
    public function isCartesian(): bool
    {
        return $this !== self::Pie && $this !== self::Donut;
    }

    /**
     * Types whose x axis can be pinch-zoomed and panned. Only the continuous
     * ones: zooming a handful of categorical bars or pie slices buys nothing.
     */
    public function supportsZoom(): bool
    {
        return $this === self::Line || $this === self::Area;
    }
}
