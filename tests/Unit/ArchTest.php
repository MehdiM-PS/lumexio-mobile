<?php

arch()->preset()->php();

arch('native components follow the SuperNative conventions')
    ->expect('App\NativeComponents\Screens')
    ->toExtend('Native\Mobile\Edge\NativeComponent')
    ->toHaveMethod('render');
