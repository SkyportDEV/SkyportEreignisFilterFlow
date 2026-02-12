<?php

namespace SkyportEreignisFilterFlow\Providers;

use Plenty\Plugin\ServiceProvider;
use Plenty\Plugin\Log\Loggable;

use SkyportEreignisFilterFlow\Flow\Filters\SkyportOrderIdListFilter;

class SkyportEreignisFilterFlowServiceProvider extends ServiceProvider
{
    use Loggable;

    public function boot(): void
    {
        // PlentyONE Flow Filter registration
        // The interface docs list the FilterDefinitionContract, but do not clearly document a container.
        // Therefore we try the container-based registration if available. If not available, Flow may auto-discover the definition.
        $containerClass = '\\Plenty\\Modules\\Flow\\Filters\\Definitions\\Containers\\FilterDefinitionContainer';

        if (class_exists($containerClass)) {
            $container = $this->getApplication()->make($containerClass);

            if (method_exists($container, 'register')) {
                $container->register(pluginApp(SkyportOrderIdListFilter::class));
            } else {
                $this->getLogger('SkyportEreignisFilterFlow')->warning('Flow filter container exists but has no register() method.');
            }
        } else {
            $this->getLogger('SkyportEreignisFilterFlow')->info('Flow filter container not found; relying on Flow auto-discovery for filter definitions.');
        }
    }
}
