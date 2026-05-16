<?php

namespace Plugin\MotionPayWebhook;

use App\Services\Plugin\AbstractPlugin;

class Plugin extends AbstractPlugin
{
    public function boot(): void
    {
        // Plugin is route-based, no hooks needed at boot
    }
}
