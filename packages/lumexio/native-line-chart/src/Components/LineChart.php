<?php

namespace Lumexio\NativeLineChart\Components;

use Native\Mobile\Edge\Components\Native\NativeBladeComponent;

class LineChart extends NativeBladeComponent
{
    protected bool $isSelfClosing = true;

    protected function elementType(): string
    {
        return 'lumexio_line_chart';
    }
}
