<?php

namespace App\NativeComponents\Layouts;

use Native\Mobile\Edge\Layouts\Builders\Tab;
use Native\Mobile\Edge\Layouts\Builders\TabBar;
use Native\Mobile\Edge\Layouts\NativeLayout;
use Native\Mobile\Edge\NativeComponent;

class TabsLayout extends NativeLayout
{
    public function tabBar(NativeComponent $screen): ?TabBar
    {
        return TabBar::make()
            ->activeColor(theme('primary'))
            ->add(Tab::link('Dashboard', '/dashboard', icon: 'home'))
            ->add(Tab::link('Stock', '/stock', icon: 'shippingbox'));
        // Later slices append more ->add(Tab::link(...)) calls here (Orders, Chat)
        // rather than creating a second layout — max 5 tabs per TabBar.
    }

    public function usesNativeChrome(): bool
    {
        return true;
    }
}
