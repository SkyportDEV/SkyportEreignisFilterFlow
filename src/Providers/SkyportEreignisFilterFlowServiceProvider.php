<?php

namespace SkyportEreignisFilterFlow\Providers;

use Plenty\Plugin\ServiceProvider;
use Plenty\Modules\Flow\Filters\Definitions\Containers\FilterDefinitionContainer;
use SkyportEreignisFilterFlow\Flow\Filters\SkyportIdListFilter;

class SkyportEreignisFilterFlowServiceProvider extends ServiceProvider
{
    public function boot(FilterDefinitionContainer $filterDefinitionContainer): void
    {
        // Wichtig: In vielen Plenty-Versionen ist das die stabilste Form:
        // => Klasse registrieren, nicht pluginApp() Instanz
        $filterDefinitionContainer->register(SkyportIdListFilter::class);
    }
}
