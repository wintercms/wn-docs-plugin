<?php

if (!class_exists(RainLab\Docs\Classes\Page::class)) {
    class_alias(Winter\Docs\Plugin::class, RainLab\Docs\Plugin::class);

    class_alias(Winter\Docs\Classes\DocsManager::class, RainLab\Docs\Classes\DocsManager::class);
    class_alias(Winter\Docs\Classes\Page::class, RainLab\Docs\Classes\Page::class);
    class_alias(Winter\Docs\Classes\PagesList::class, RainLab\Docs\Classes\PagesList::class);

    class_alias(Winter\Docs\Controllers\Base::class, RainLab\Docs\Controllers\Base::class);
    class_alias(Winter\Docs\Controllers\Index::class, RainLab\Docs\Controllers\Index::class);
}
