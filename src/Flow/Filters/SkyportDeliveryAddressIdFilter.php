<?php

namespace SkyportEreignisFilterFlow\Flow\Filters;

use Plenty\Modules\Flow\Contracts\UIConfigFormContract;
use Plenty\Modules\Flow\DataModels\ConfigForm\NumberField;
use Plenty\Modules\Flow\Enums\FilterOperators;
use Plenty\Modules\Flow\Filters\Definitions\Models\Plugin\PluginFlowFilterDefinition;
use Plenty\Modules\Order\Contracts\OrderRepositoryContract;

class SkyportDeliveryAddressIdFilter extends PluginFlowFilterDefinition
{
    const IDENTIFIER = 'SkyportEreignisFilterFlow::deliveryAddressId';
    const KEY = 'deliveryAddressId';

    public function getIdentifier(): string
    {
        return self::IDENTIFIER;
    }

    public function getName(): string
    {
        return 'Skyport: Lieferadresse-ID';
    }

    public function getDescription(): string
    {
        return 'Filtert Aufträge anhand der Lieferadress-ID.';
    }

    public function getAIDescription(): string
    {
        return 'Filters orders by delivery address ID.';
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

        /** @var NumberField $addressIdField */
        $addressIdField = $this->getFormField(
            NumberField::class,
            [
                'name' => self::KEY,
                'label' => 'Lieferadresse-ID'
            ]
        );

        $configForm->addNumberField(
            $addressIdField,
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

        if (
            !$order ||
            !isset($order->deliveryAddress) ||
            !isset($order->deliveryAddress->id)
        ) {
            return false;
        }

        if (
            !isset($filterField[self::KEY]) ||
            !isset($filterField[self::KEY]['operator']) ||
            !isset($filterField[self::KEY]['value'])
        ) {
            return false;
        }

        $addressId = (int)$order->deliveryAddress->id;
        $configuredId = (int)$filterField[self::KEY]['value'];
        $operator = $filterField[self::KEY]['operator'];

        $this->captureGiven(
            self::KEY,
            $addressId
        );

        if ($operator === FilterOperators::EQUAL) {
            return $addressId === $configuredId;
        }
        
        if ($operator === FilterOperators::NOT_EQUAL) {
            return $addressId !== $configuredId;
        }
        
        return false;

        return false;
    }
}
