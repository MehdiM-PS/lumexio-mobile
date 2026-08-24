<?php

namespace Lumexio\NativeCharts;

use Illuminate\Support\ServiceProvider;
use Lumexio\NativeCharts\Elements\Chart;
use Native\Mobile\Edge\ElementRegistry;

class NativeChartsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    /**
     * Registering the element type is all the wiring the package needs:
     * NativePHP's tag precompiler turns `<native:lumexio-chart …>` straight
     * into a `lumexio_chart` collector call, and the collector resolves that
     * type through the ElementRegistry.
     *
     * Unlike a `nativephp-ui-plugin`, this package ships no Swift/Kotlin
     * renderer and so does not belong in `NativeServiceProvider::plugins()`
     * — the node it produces is a core `webview` one (see {@see Chart}),
     * which both platforms already render.
     */
    public function boot(): void
    {
        ElementRegistry::register('lumexio_chart', Chart::class);
    }
}
