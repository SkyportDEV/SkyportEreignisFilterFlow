<?php

namespace SkyportEreignisFilterFlow\Flow\Filters;

use Plenty\Modules\Flow\Contracts\UIConfigFormContract;
use Plenty\Modules\Flow\DataModels\ConfigForm\SelectboxField;
use Plenty\Modules\Flow\DataModels\ConfigForm\TextAreaField;
use Plenty\Modules\Flow\Filters\Definitions\Models\Plugin\PluginFlowFilterDefinition;
use Plenty\Modules\Order\Contracts\OrderRepositoryContract;

class SkyportContactIdFilter extends PluginFlowFilterDefinition
{
    const IDENTIFIER = 'SkyportEreignisFilterFlow::contactId';
    const KEY_MODE = 'mode';
    const KEY_IDS = 'ids';

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
        return [];
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

        /** @var SelectboxField $modeField */
        $modeField = $this->getFormField(
            SelectboxField::class,
            [
                'name' => self::KEY_MODE,
                'label' => 'Modus'
            ]
        );

        $modeField->addSelectboxValue(
            'Zulassen',
            'allow',
            false
        );

        $modeField->addSelectboxValue(
            'Nicht zulassen',
            'deny',
            false
        );

        $configForm->addSelectboxField(
            $modeField,
            self::KEY_MODE
        );

        /** @var TextAreaField $idsField */
        $idsField = $this->getFormField(
            TextAreaField::class,
            [
                'name' => self::KEY_IDS,
                'label' => 'Kontakt-IDs'
            ]
        );

        $idsField->helperText = 'Eine ID pro Zeile oder durch Komma getrennt.';

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

        if (!$order || !isset($order->contactReceiverId)) {
            return false;
        }

        if (
            !isset($filterField[self::KEY_MODE]['value']) ||
            !isset($filterField[self::KEY_IDS]['value'])
        ) {
            return false;
        }

        $mode = (string)$filterField[self::KEY_MODE]['value'];
        $idsRaw = (string)$filterField[self::KEY_IDS]['value'];

        $ids = $this->parseIds($idsRaw);

        if (count($ids) === 0) {
            return false;
        }

        $value = (int)$order->contactReceiverId;

        $this->captureGiven(
            self::KEY_IDS,
            $value
        );

        $inList = in_array(
            $value,
            $ids,
            true
        );

        if ($mode === 'deny') {
            return !$inList;
        }

        return $inList;
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
