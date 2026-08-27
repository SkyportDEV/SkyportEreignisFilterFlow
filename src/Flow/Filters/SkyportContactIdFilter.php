<?php

namespace SkyportEreignisFilterFlow\Flow\Filters;

use Plenty\Modules\Account\Contact\Contracts\ContactRepositoryContract;
use Plenty\Modules\Flow\Contracts\UIConfigFormContract;
use Plenty\Modules\Flow\DataModels\ConfigForm\CheckboxGroupField;
use Plenty\Modules\Flow\DataModels\ConfigForm\SelectboxField;
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
        return 'Filters orders by receiver contact ID. Supports a single contact or multiple contacts.';
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
         * Operator an denselben KEY binden wie die Wertefelder.
         */
        $configForm = $this->addOperators(
            $configForm,
            self::KEY
        );

        /*
         * Einzelwert für EQUAL.
         */
        /** @var SelectboxField $contactSelectBox */
        $contactSelectBox = $this->getFormField(
            SelectboxField::class,
            [
                'name' => self::KEY,
                'label' => 'Kontakt'
            ]
        );

        /*
         * Mehrfachauswahl für IN / NOT_IN.
         */
        /** @var CheckboxGroupField $contactCheckBoxGroup */
        $contactCheckBoxGroup = $this->getFormField(
            CheckboxGroupField::class,
            [
                'name' => self::KEY,
                'label' => 'Kontakte'
            ]
        );

        /*
         * Wie im offiziellen Plenty-Beispiel:
         *
         * Selectbox nur bei Einzeloperatoren.
         * CheckboxGroup nur bei IN / NOT_IN.
         *
         * Plenty verwendet intern für NOT_IN den Operatorwert "NIN".
         */
        $contactSelectBox->condition = 'operator != "IN" && operator != "NIN"';
        $contactSelectBox->conditionKeys = ['operator'];

        $contactCheckBoxGroup->condition = 'operator == "IN" || operator == "NIN"';
        $contactCheckBoxGroup->conditionKeys = ['operator'];

        /*
         * Kontakte aus diesem Plenty-System laden.
         */
        /** @var ContactRepositoryContract $contactRepository */
        $contactRepository = pluginApp(
            ContactRepositoryContract::class
        );

        $page = 1;
        $itemsPerPage = 250;

        do {
            $contacts = $contactRepository->getContactList(
                [],
                [],
                [
                    'id',
                    'firstName',
                    'lastName',
                    'fullName',
                    'email'
                ],
                $page,
                $itemsPerPage,
                'id',
                'asc'
            );

            foreach ($contacts->getResult() as $contact) {
                $contactId = isset($contact->id)
                    ? (int)$contact->id
                    : 0;

                if ($contactId <= 0) {
                    continue;
                }

                $label = $this->buildContactLabel($contact);

                $contactSelectBox->addSelectboxValue(
                    $label,
                    $contactId,
                    false
                );

                $contactCheckBoxGroup->addCheckBoxValue(
                    $label,
                    $contactId,
                    false
                );
            }

            $page++;
        } while (!$contacts->isLastPage());

        /*
         * Beide Felder unter demselben KEY registrieren.
         * Flow blendet je nach Operator das passende Feld ein.
         */
        $configForm->addSelectboxField(
            $contactSelectBox,
            self::KEY
        );

        $configForm->addCheckboxGroupField(
            $contactCheckBoxGroup,
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
            !isset($order->contactReceiverId)
        ) {
            return false;
        }

        $contactId = (int)$order->contactReceiverId;

        if ($contactId <= 0) {
            return false;
        }

        if (
            !isset($filterField[self::KEY]) ||
            !isset($filterField[self::KEY]['operator']) ||
            !isset($filterField[self::KEY]['value'])
        ) {
            return false;
        }

        $operator = $filterField[self::KEY]['operator'];
        $configuredValue = $filterField[self::KEY]['value'];

        if ($operator === FilterOperators::EQUAL) {
            $result = $contactId === (int)$configuredValue;
        }
        elseif ($operator === FilterOperators::IN) {
            $result = is_array($configuredValue)
                && in_array($contactId, $configuredValue, true);
        }
        elseif ($operator === FilterOperators::NOT_IN) {
            $result = is_array($configuredValue)
                && !in_array($contactId, $configuredValue, true);
        }
        else {
            $result = false;
        }

        /*
         * Im FlowTracker sichtbar machen,
         * welche Contact-ID der Auftrag tatsächlich hatte.
         */
        $this->captureGiven(
            self::KEY,
            $contactId
        );

        return $result;
    }

    private function buildContactLabel($contact): string
    {
        $id = isset($contact->id)
            ? (int)$contact->id
            : 0;

        $name = '';

        if (
            isset($contact->fullName) &&
            trim((string)$contact->fullName) !== ''
        ) {
            $name = trim((string)$contact->fullName);
        }
        else {
            $firstName = isset($contact->firstName)
                ? trim((string)$contact->firstName)
                : '';

            $lastName = isset($contact->lastName)
                ? trim((string)$contact->lastName)
                : '';

            $name = trim($firstName . ' ' . $lastName);
        }

        $email = isset($contact->email)
            ? trim((string)$contact->email)
            : '';

        $label = (string)$id;

        if ($name !== '') {
            $label .= ' - ' . $name;
        }

        if ($email !== '') {
            $label .= ' - ' . $email;
        }

        return $label;
    }
}
