<?php

namespace SkyportEreignisFilterFlow\Providers;

use Plenty\Plugin\ServiceProvider;
use Plenty\Modules\Flow\Services\PluginFlowRegistrationService;
use SkyportEreignisFilterFlow\Flow\Filters\SkyportContactIdFilter;
use SkyportEreignisFilterFlow\Flow\Filters\SkyportBillingAddressIdFilter;
use SkyportEreignisFilterFlow\Flow\Filters\SkyportDeliveryAddressIdFilter;

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

        $registrationService->registerFilter(
            pluginApp(SkyportBillingAddressIdFilter::class)
        );

        $registrationService->registerFilter(
            pluginApp(SkyportDeliveryAddressIdFilter::class)
        );
    }
}
