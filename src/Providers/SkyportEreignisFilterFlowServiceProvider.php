<?php

namespace SkyportEreignisFilterFlow\Providers;

use Plenty\Plugin\ServiceProvider;
use Plenty\Modules\Flow\Filters\Definitions\Containers\FilterDefinitionContainer;
use SkyportEreignisFilterFlow\Flow\Filters\SkyportAlwaysTrueFilter;

class SkyportEreignisFilterFlowServiceProvider extends ServiceProvider
{
    public function boot(FilterDefinitionContainer $container): void
    {
        $container->register(pluginApp(SkyportAlwaysTrueFilter::class));
    }
}
