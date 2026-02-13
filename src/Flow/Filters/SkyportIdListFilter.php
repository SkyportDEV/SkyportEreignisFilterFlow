<?php

namespace SkyportEreignisFilterFlow\Flow\Filters;

use Plenty\Modules\Flow\Filters\Definitions\Contracts\FilterDefinitionContract;

class SkyportAlwaysTrueFilter extends FilterDefinitionContract
{
    public function getIdentifier(): string
    {
        return 'skyport_always_true';
    }

    public function getName(): string
    {
        return 'Skyport: TEST Filter (Always True)';
    }

    public function getDescription(): string
    {
        return 'Testfilter – immer wahr. Nur um zu prüfen, ob Custom Flow Filter grundsätzlich angezeigt werden.';
    }

    public function getUIConfigFields(): array
    {
        return [];
    }

    public function getRequiredInputTypes(): array
    {
        return ['order'];
    }

    public function getOperators(): array
    {
        return [];
    }

    public function getAvailabilities(): array
    {
        return [];
    }

    public function getCondition(): bool
    {
        return true;
    }

    public function performFilter($inputs, $filterField, $extraParams = []): bool
    {
        return true;
    }
}
