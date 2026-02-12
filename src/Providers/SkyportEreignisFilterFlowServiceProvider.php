<?php

namespace SkyportEreignisFilterFlow\Providers;

use Plenty\Plugin\ServiceProvider;
use Plenty\Modules\Flow\Filters\Definitions\Containers\FilterDefinitionContainer;
use SkyportEreignisFilterFlow\Flow\Filters\SkyportOrderIdListFilter;

class SkyportEreignisFilterFlowServiceProvider extends ServiceProvider
{
    public function boot(FilterDefinitionContainer $filterDefinitionContainer): void
    {
        // Register Flow filter definition (configured directly inside PlentyONE Flow)
        $filterDefinitionContainer->register(pluginApp(SkyportOrderIdListFilter::class));
    }
}
