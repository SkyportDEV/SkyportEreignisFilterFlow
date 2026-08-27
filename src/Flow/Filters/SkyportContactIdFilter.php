<?php

namespace SkyportEreignisFilterFlow\Flow\Filters;

use Plenty\Modules\Flow\Contracts\UIConfigFormContract;
use Plenty\Modules\Flow\DataModels\ConfigForm\NumberField;
use Plenty\Modules\Flow\Enums\FilterOperators;
use Plenty\Modules\Flow\Filters\Definitions\Models\Plugin\PluginFlowFilterDefinition;
use Plenty\Modules\Order\Contracts\OrderRepositoryContract;

class SkyportContactIdFilter extends PluginFlowFilterDefinition
{
    const IDENTIFIER = 'SkyportEreignisFilterFlow::contactId';
    const KEY = 'contactId';

    public function getIdentifier(): string
    {
        return self::IDENTIFIER;
    }

    public function getName(): string
    {
        return 'Skyport: Kontakt-ID';
    }

    public function getDescription(): string
    {
        return 'Filtert Aufträge anhand der Empfänger-Kontakt-ID.';
    }

    public function getAIDescription(): string
    {
        return 'Filters orders by receiver contact ID.';
    }

    public function getOperators(): array
    {
        return [
            FilterOperators::EQUAL,
            FilterOperators::NOT_EQUAL
        ];
    }

    public function getUIConfigFields(): array
    {
        /** @var UIConfigFormContract $configForm */
        $configForm = pluginApp(
            UIConfigFormContract::class,
            [
                'translationNamespace' => 'module_flow'
            ]
        );

        $configForm = $this->addOperators(
            $configForm,
            self::KEY
        );

        /** @var NumberField $contactIdField */
        $contactIdField = $this->getFormField(
            NumberField::class,
            [
                'name' => self::KEY,
                'label' => 'Kontakt-ID'
            ]
        );

        $configForm->addNumberField(
            $contactIdField,
            self::KEY
        );

        return $configForm->getConfigFields();
    }

    public function performFilter(
        array $inputs,
        array $filterField,
        array $extraParams = []
    ): bool {
        $filterField = $this->mapFilterFields($filterField);

        if (!isset($inputs[$this->getObjectType()])) {
            return false;
        }

        $orderId = (int)$inputs[$this->getObjectType()]->value;

        if ($orderId <= 0) {
            return false;
        }

        /** @var OrderRepositoryContract $orderRepository */
        $orderRepository = pluginApp(
            OrderRepositoryContract::class
        );

        $order = $orderRepository->findById($orderId);

        if (!$order || !isset($order->contactReceiverId)) {
            return false;
        }

        if (
            !isset($filterField[self::KEY]) ||
            !isset($filterField[self::KEY]['operator']) ||
            !isset($filterField[self::KEY]['value'])
        ) {
            return false;
        }

        $contactId = (int)$order->contactReceiverId;
        $configuredId = (int)$filterField[self::KEY]['value'];
        $operator = $filterField[self::KEY]['operator'];

        $this->captureGiven(
            self::KEY,
            $contactId
        );

        if ($operator === FilterOperators::EQUAL) {
            return $contactId === $configuredId;
        }
        
        if ($operator === FilterOperators::NOT_EQUAL) {
            return $contactId !== $configuredId;
        }
        
        return false;

        return false;
    }
}
