<?php

namespace SkyportEreignisFilterFlow\Flow\Filters;

use Plenty\Modules\Flow\Contracts\UIConfigFormContract;
use Plenty\Modules\Flow\DataModels\ConfigForm\CheckboxGroupField;
use Plenty\Modules\Flow\DataModels\ConfigForm\TextAreaField;
use Plenty\Modules\Flow\Enums\FilterOperators;
use Plenty\Modules\Flow\Filters\Definitions\Models\Plugin\PluginFlowFilterDefinition;
use Plenty\Modules\Order\Contracts\OrderRepositoryContract;

class SkyportBillingAddressIdFilter extends PluginFlowFilterDefinition
{
    const IDENTIFIER = 'SkyportEreignisFilterFlow::billingAddressId';
    const KEY_OPERATOR = 'billingAddressIdOperator';
    const KEY_IDS = 'billingAddressIds';

    public function getIdentifier(): string
    {
        return self::IDENTIFIER;
    }

    public function getName(): string
    {
        return 'Skyport: Rechnungsadresse-ID';
    }

    public function getDescription(): string
    {
        return 'Filtert Aufträge anhand der Rechnungsadress-ID.';
    }

    public function getAIDescription(): string
    {
        return 'Filters orders by billing address ID.';
    }

    public function getOperators(): array
    {
        return [
            FilterOperators::EQUAL,
            FilterOperators::IN,
            FilterOperators::NOT_IN
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
            self::KEY_OPERATOR
        );

        /** @var CheckboxGroupField $operatorValueField */
        $operatorValueField = $this->getFormField(
            CheckboxGroupField::class,
            [
                'name' => self::KEY_OPERATOR,
                'label' => ''
            ]
        );

        $operatorValueField->addCheckBoxValue(
            '',
            'dummy',
            false
        );

        $operatorValueField->isVisible = false;
        $operatorValueField->isRequired = false;

        $configForm->addCheckboxGroupField(
            $operatorValueField,
            self::KEY_OPERATOR
        );

        /** @var TextAreaField $idsField */
        $idsField = $this->getFormField(
            TextAreaField::class,
            [
                'name' => self::KEY_IDS,
                'label' => 'Rechnungsadress-IDs'
            ]
        );

        $idsField->helperText = 'Eine ID pro Zeile oder durch Komma getrennt. Bei "Ist gleich" genau eine ID eingeben.';
        $idsField->isRequired = true;

        $configForm->addTextAreaField(
            $idsField,
            self::KEY_IDS
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

        if (!$order) {
            return false;
        }

        if (
            !isset($order->billingAddress) ||
            !isset($order->billingAddress->id)
        ) {
            return false;
        }

        $addressId = (int)$order->billingAddress->id;

        if ($addressId <= 0) {
            return false;
        }

        if (
            !isset($filterField[self::KEY_OPERATOR]) ||
            !isset($filterField[self::KEY_OPERATOR]['operator'])
        ) {
            return false;
        }

        $operator = $filterField[self::KEY_OPERATOR]['operator'];

        if (
            !isset($filterField[self::KEY_IDS]) ||
            !isset($filterField[self::KEY_IDS]['value'])
        ) {
            return false;
        }

        $idsRaw = (string)$filterField[self::KEY_IDS]['value'];
        $ids = $this->parseIds($idsRaw);

        if (count($ids) === 0) {
            return false;
        }

        $this->captureGiven(
            self::KEY_IDS,
            $addressId
        );

        if ($operator === FilterOperators::EQUAL) {
            if (count($ids) !== 1) {
                return false;
            }

            return $addressId === $ids[0];
        }

        if ($operator === FilterOperators::IN) {
            return in_array(
                $addressId,
                $ids,
                true
            );
        }

        if ($operator === FilterOperators::NOT_IN) {
            return !in_array(
                $addressId,
                $ids,
                true
            );
        }

        return false;
    }

    private function parseIds(string $input): array
    {
        $input = str_replace(
            [
                "\r\n",
                "\r",
                "\n"
            ],
            ',',
            $input
        );

        $ids = [];
        $seen = [];

        foreach (explode(',', $input) as $part) {
            $part = trim($part);

            if ($part === '') {
                continue;
            }

            $id = (int)$part;

            if ($id <= 0) {
                continue;
            }

            if (!isset($seen[$id])) {
                $seen[$id] = true;
                $ids[] = $id;
            }
        }

        return $ids;
    }
}
