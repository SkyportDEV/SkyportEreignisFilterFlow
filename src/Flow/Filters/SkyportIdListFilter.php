<?php

namespace SkyportEreignisFilterFlow\Flow\Filters;

use Plenty\Modules\Flow\Filters\Definitions\Contracts\FilterDefinitionContract;
use Plenty\Modules\Flow\DataModels\ConfigForm\SelectboxField;
use Plenty\Modules\Flow\DataModels\ConfigForm\SelectboxValue;
use Plenty\Modules\Flow\DataModels\ConfigForm\TextAreaField;

class SkyportIdListFilter extends FilterDefinitionContract
{
    public function getIdentifier(): string
    {
        return 'skyport_id_list_filter';
    }

    public function getName(): string
    {
        // Wichtig: Suchbegriff "Kontakt" direkt im Namen
        return 'Skyport: Kontakt/Adressen – ID Liste';
    }

    public function getDescription(): string
    {
        return 'Filtert Orders nach Kontakt-ID (Empfänger) oder Rechnungs-/Lieferadresse per ID-Liste.';
    }

    public function getUIConfigFields(): array
    {
        $type = pluginApp(SelectboxField::class);
        $type->name = 'type';
        $type->caption = 'Typ';
        $type->value = 'contact';
        $type->selectBoxValues = [
            $this->sbv('contact', 'Kontakt-ID (Empfänger)'),
            $this->sbv('billing', 'ID der Rechnungsadresse'),
            $this->sbv('delivery', 'ID der Lieferadresse'),
        ];

        $mode = pluginApp(SelectboxField::class);
        $mode->name = 'mode';
        $mode->caption = 'Modus';
        $mode->value = 'allow';
        $mode->selectBoxValues = [
            $this->sbv('allow', 'Zulassen (Treffer = true)'),
            $this->sbv('deny', 'Nicht zulassen (Treffer = false)'),
        ];

        $ids = pluginApp(TextAreaField::class);
        $ids->name = 'ids';
        $ids->caption = 'IDs (Komma oder Zeilenumbrüche – gemischt möglich)';
        $ids->value = '';

        return [
            $type->toArray(),
            $mode->toArray(),
            $ids->toArray(),
        ];
    }

    public function getRequiredInputTypes(): array
    {
        return ['order'];
    }

    public function getOperators(): array
    {
        // konservativ leer lassen
        return [];
    }

    public function getAvailabilities(): array
    {
        // konservativ leer lassen (UI entscheidet)
        return [];
    }

    public function getCondition(): bool
    {
        return true;
    }

    public function performFilter($inputs, $filterField, $extraParams = []): bool
    {
        $order = $this->extractOrder($inputs);
        if (!$order) {
            return false;
        }

        $type = $this->getConfigValue($filterField, 'type', 'contact');
        $mode = $this->getConfigValue($filterField, 'mode', 'allow');
        $idsRaw = $this->getConfigValue($filterField, 'ids', '');

        $ids = $this->parseIds($idsRaw);
        if (count($ids) === 0) {
            return false;
        }

        $value = 0;

        if ($type === 'contact') {
            $value = isset($order->contactReceiverId) ? (int)$order->contactReceiverId : 0;
        } elseif ($type === 'billing') {
            $value = (isset($order->billingAddress) && isset($order->billingAddress->id))
                ? (int)$order->billingAddress->id
                : 0;
        } elseif ($type === 'delivery') {
            $value = (isset($order->deliveryAddress) && isset($order->deliveryAddress->id))
                ? (int)$order->deliveryAddress->id
                : 0;
        }

        if ($value <= 0) {
            return false;
        }

        $inList = in_array($value, $ids, true);

        if ($mode === 'deny') {
            return !$inList;
        }

        return $inList;
    }

    private function sbv(string $value, string $caption): array
    {
        $o = pluginApp(SelectboxValue::class);
        $o->value = $value;
        $o->caption = $caption;
        $o->translateCaption = false;
        return $o->toArray();
    }

    private function extractOrder($inputs)
    {
        if (is_array($inputs) && isset($inputs['order'])) {
            return $inputs['order'];
        }

        if (is_array($inputs)) {
            foreach ($inputs as $v) {
                if (is_object($v)) {
                    return $v;
                }
            }
        }

        return null;
    }

    private function getConfigValue($filterField, string $key, string $default): string
    {
        if (is_array($filterField)) {
            if (isset($filterField['configFields']) && is_array($filterField['configFields']) && isset($filterField['configFields'][$key])) {
                return (string)$filterField['configFields'][$key];
            }

            if (isset($filterField['config']) && is_array($filterField['config']) && isset($filterField['config'][$key])) {
                return (string)$filterField['config'][$key];
            }

            if (isset($filterField[$key])) {
                return (string)$filterField[$key];
            }
        }

        return $default;
    }

    private function parseIds(string $input): array
    {
        $input = str_replace(["\r\n", "\r", "\n"], ",", $input);

        $out = [];
        foreach (explode(",", $input) as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }

            $id = (int)$part;
            if ($id > 0) {
                $out[] = $id;
            }
        }

        $unique = [];
        $seen = [];
        foreach ($out as $id) {
            if (!isset($seen[$id])) {
                $seen[$id] = 1;
                $unique[] = $id;
            }
        }

        return $unique;
    }
}
