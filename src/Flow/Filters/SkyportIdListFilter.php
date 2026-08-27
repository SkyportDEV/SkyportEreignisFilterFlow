<?php

namespace SkyportEreignisFilterFlow\Flow\Filters;

use Plenty\Modules\Flow\Contracts\UIConfigFormContract;
use Plenty\Modules\Flow\DataModels\ConfigForm\SelectboxField;
use Plenty\Modules\Flow\DataModels\ConfigForm\TextAreaField;
use Plenty\Modules\Flow\Enums\FilterOperators;
use Plenty\Modules\Flow\Filters\Definitions\Models\Plugin\PluginFlowFilterDefinition;
use Plenty\Modules\Order\Contracts\OrderRepositoryContract;

class SkyportIdListFilter extends PluginFlowFilterDefinition
{
    const IDENTIFIER = 'SkyportEreignisFilterFlow::orderIdList';
    const KEY_TYPE = 'idType';
    const KEY_IDS = 'ids';

    public function getIdentifier(): string
    {
        return self::IDENTIFIER;
    }

    public function getName(): string
    {
        return 'Skyport Ereignis-Filter';
    }

    public function getDescription(): string
    {
        return 'Filtert Aufträge nach Kontakt-ID, Rechnungsadress-ID oder Lieferadress-ID.';
    }

    public function getAIDescription(): string
    {
        return 'Filter orders by receiver contact ID, billing address ID or delivery address ID.';
    }

    public function getOperators(): array
    {
        return [
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

        /*
         * Plenty fügt damit den Operator für KEY_IDS hinzu:
         *
         * IN     = Zulassen
         * NOT_IN = Nicht zulassen
         */
        $configForm = $this->addOperators(
            $configForm,
            self::KEY_IDS
        );

        /*
         * Typ der zu prüfenden ID
         */
        /** @var SelectboxField $typeField */
        $typeField = $this->getFormField(
            SelectboxField::class,
            [
                'name' => self::KEY_TYPE,
                'label' => 'Typ'
            ]
        );

        $typeField->addSelectboxValue(
            'Kontakt-ID',
            'contact',
            false
        );

        $typeField->addSelectboxValue(
            'ID der Rechnungsadresse',
            'billing',
            false
        );

        $typeField->addSelectboxValue(
            'ID der Lieferadresse',
            'delivery',
            false
        );

        $configForm->addSelectboxField(
            $typeField,
            self::KEY_TYPE
        );

        /*
         * ID-Liste
         *
         * Erlaubt:
         * 123
         * 456
         *
         * oder:
         * 123,456
         *
         * oder gemischt.
         */
        /** @var TextAreaField $idsField */
        $idsField = $this->getFormField(
            TextAreaField::class,
            [
                'name' => self::KEY_IDS,
                'label' => 'IDs'
            ]
        );

        $idsField->helperText = 'Eine ID pro Zeile oder durch Komma getrennt. Beide Varianten können gemischt werden.';

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
        /*
         * Wichtig:
         * Flow liefert hier nicht das Order-Modell direkt.
         *
         * mapFilterFields() erzeugt:
         *
         * [
         *     'ids' => [
         *         'operator' => ...,
         *         'value' => ...
         *     ],
         *     ...
         * ]
         */
        $filterField = $this->mapFilterFields($filterField);

        /*
         * Order-ID aus dem Flow-Input holen.
         *
         * Genau dieses Muster verwendet auch das offizielle
         * Plenty-Beispiel.
         */
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

        /*
         * Konfiguration auslesen
         */
        if (
            !isset($filterField[self::KEY_TYPE]) ||
            !isset($filterField[self::KEY_TYPE]['value']) ||
            !isset($filterField[self::KEY_IDS]) ||
            !isset($filterField[self::KEY_IDS]['operator']) ||
            !isset($filterField[self::KEY_IDS]['value'])
        ) {
            return false;
        }

        $type = (string)$filterField[self::KEY_TYPE]['value'];
        $operator = $filterField[self::KEY_IDS]['operator'];
        $idsRaw = (string)$filterField[self::KEY_IDS]['value'];

        $ids = $this->parseIds($idsRaw);

        if (count($ids) === 0) {
            return false;
        }

        /*
         * Tatsächliche ID des Auftrags ermitteln
         */
        $value = 0;

        if ($type === 'contact') {
            if (isset($order->contactReceiverId)) {
                $value = (int)$order->contactReceiverId;
            }
        }
        elseif ($type === 'billing') {
            if (
                isset($order->billingAddress) &&
                isset($order->billingAddress->id)
            ) {
                $value = (int)$order->billingAddress->id;
            }
        }
        elseif ($type === 'delivery') {
            if (
                isset($order->deliveryAddress) &&
                isset($order->deliveryAddress->id)
            ) {
                $value = (int)$order->deliveryAddress->id;
            }
        }

        /*
         * Tatsächlichen Wert für den FlowTracker erfassen.
         *
         * Damit sollte später im Tracker sichtbar sein,
         * welche ID Plenty tatsächlich geprüft hat.
         */
        $this->captureGiven(
            self::KEY_IDS,
            $value
        );

        if ($value <= 0) {
            return false;
        }

        $inList = in_array(
            $value,
            $ids,
            true
        );

        if ($operator === FilterOperators::IN) {
            return $inList;
        }

        if ($operator === FilterOperators::NOT_IN) {
            return !$inList;
        }

        return false;
    }

    private function parseIds(string $input): array
    {
        /*
         * Windows / Unix / Mac Zeilenumbrüche zu Komma
         */
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
