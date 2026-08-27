<?php

namespace SkyportEreignisFilterFlow\Providers;

use Plenty\Plugin\ServiceProvider;
use Plenty\Modules\Flow\Services\PluginFlowRegistrationService;
use SkyportEreignisFilterFlow\Flow\Filters\SkyportIdListFilter;

class SkyportEreignisFilterFlowServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $pluginFlowRegistrationService = pluginApp(
            PluginFlowRegistrationService::class
        );

        $pluginFlowRegistrationService->registerFilter(
            pluginApp(SkyportIdListFilter::class)
        );
    }
}
