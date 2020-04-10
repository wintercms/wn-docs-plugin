<?php

Route::group([
    'prefix' => Config::get('cms.backendUri', 'backend') . '/docs',
    'middleware' => 'web'
], function () {
    Route::any('{slug?}', 'RainLab\Docs\Controllers\Base@run')->where('slug', '(.*)?');
});
