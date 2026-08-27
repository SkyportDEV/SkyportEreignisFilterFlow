<?php

namespace SkyportEreignisFilterFlow\Providers;

use Plenty\Plugin\ServiceProvider;
use Plenty\Modules\Flow\Services\PluginFlowRegistrationService;
use SkyportEreignisFilterFlow\Flow\Filters\SkyportContactIdFilter;

class SkyportEreignisFilterFlowServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $registrationService = pluginApp(
            PluginFlowRegistrationService::class
        );

        $registrationService->registerFilter(
            pluginApp(SkyportContactIdFilter::class)
        );
    }
}
