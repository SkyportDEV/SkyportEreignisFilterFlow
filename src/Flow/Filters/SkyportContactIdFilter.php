<?php

namespace SkyportEreignisFilterFlow\Flow\Filters;

use Plenty\Modules\Flow\Contracts\UIConfigFormContract;
use Plenty\Modules\Flow\DataModels\ConfigForm\CheckboxGroupField;
use Plenty\Modules\Flow\DataModels\ConfigForm\TextAreaField;
use Plenty\Modules\Flow\Enums\FilterOperators;
use Plenty\Modules\Flow\Filters\Definitions\Models\Plugin\PluginFlowFilterDefinition;
use Plenty\Modules\Order\Contracts\OrderRepositoryContract;

class SkyportContactIdFilter extends PluginFlowFilterDefinition
{
    const IDENTIFIER = 'SkyportEreignisFilterFlow::contactId';

    /*
     * An diesem technischen Feld hängt der Plenty-Operator.
     *
     * Das Feld selbst ist unsichtbar. Es existiert nur, weil Plenty
     * insbesondere für IN / NOT_IN ein Feld mit "options" erwartet.
     */
    const KEY_OPERATOR = 'contactIdOperator';

    /*
     * Hier werden die eigentlichen Kontakt-IDs eingegeben.
     */
    const KEY_IDS = 'contactIds';

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
         * Plenty-Operator hinzufügen:
         *
         * =       Ist gleich
         * IN      Ist in
         * NOT_IN  Ist nicht in
         */
        $configForm = $this->addOperators(
            $configForm,
            self::KEY_OPERATOR
        );

        /*
         * Technisches Wertefeld für Plenty.
         *
         * IN / NOT_IN erwarten intern ein Feld mit "options".
         * Deshalb verwenden wir ein CheckboxGroupField mit einer
         * Dummy-Option. Das Feld bleibt unsichtbar und wird von
         * unserer Filterlogik nicht ausgewertet.
         */
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

        /*
         * Eigentliche Kontakt-ID-Eingabe.
         *
         * Beispiele:
         *
         * 311828
         *
         * oder:
         *
         * 311828
         * 123456
         * 789012
         *
         * oder:
         *
         * 311828,123456,789012
         *
         * Auch gemischt möglich.
         */
        /** @var TextAreaField $idsField */
        $idsField = $this->getFormField(
            TextAreaField::class,
            [
                'name' => self::KEY_IDS,
                'label' => 'Kontakt-IDs'
            ]
        );

        /*
         * In deiner Plenty-Version ist helperText ein STRING.
         * Kein Array verwenden!
         */
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
        /*
         * Plenty-Filterfelder normalisieren.
         */
        $filterField = $this->mapFilterFields($filterField);

        /*
         * Flow liefert uns die Order-ID.
         */
        if (!isset($inputs[$this->getObjectType()])) {
            return false;
        }

        $orderId = (int)$inputs[$this->getObjectType()]->value;

        if ($orderId <= 0) {
            return false;
        }

        /*
         * Auftrag vollständig laden.
         */
        /** @var OrderRepositoryContract $orderRepository */
        $orderRepository = pluginApp(
            OrderRepositoryContract::class
        );

        $order = $orderRepository->findById($orderId);

        if (!$order) {
            return false;
        }

        /*
         * Kontakt-ID des Empfängers.
         */
        if (!isset($order->contactReceiverId)) {
            return false;
        }

        $contactId = (int)$order->contactReceiverId;

        if ($contactId <= 0) {
            return false;
        }

        /*
         * Operator ermitteln.
         */
        if (
            !isset($filterField[self::KEY_OPERATOR]) ||
            !isset($filterField[self::KEY_OPERATOR]['operator'])
        ) {
            return false;
        }

        $operator = $filterField[self::KEY_OPERATOR]['operator'];

        /*
         * ID-Liste aus der Textarea holen.
         */
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

        /*
         * Im FlowTracker anzeigen, welche Kontakt-ID der
         * aktuelle Auftrag tatsächlich hat.
         */
        $this->captureGiven(
            self::KEY_IDS,
            $contactId
        );

        /*
         * IST GLEICH
         *
         * Für "=" muss genau eine ID konfiguriert sein.
         */
        if ($operator === FilterOperators::EQUAL) {
            if (count($ids) !== 1) {
                return false;
            }

            return $contactId === $ids[0];
        }

        /*
         * IST IN
         */
        if ($operator === FilterOperators::IN) {
            return in_array(
                $contactId,
                $ids,
                true
            );
        }

        /*
         * IST NICHT IN
         */
        if ($operator === FilterOperators::NOT_IN) {
            return !in_array(
                $contactId,
                $ids,
                true
            );
        }

        return false;
    }

    private function parseIds(string $input): array
    {
        /*
         * Windows / Unix / Mac Zeilenumbrüche vereinheitlichen.
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

            /*
             * Duplikate vermeiden.
             */
            if (!isset($seen[$id])) {
                $seen[$id] = true;
                $ids[] = $id;
            }
        }

        return $ids;
    }
}
