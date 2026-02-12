<?php

namespace SkyportEreignisFilterFlow\Flow\Filters;

use Plenty\Modules\Flow\Filters\Definitions\Contracts\FilterDefinitionContract;
use Plenty\Modules\Flow\DataModels\ConfigForm\SelectboxField;
use Plenty\Modules\Flow\DataModels\ConfigForm\SelectboxValue;
use Plenty\Modules\Flow\DataModels\ConfigForm\TextAreaField;
use Plenty\Modules\Flow\DataModels\ConfigForm\InputField;

class SkyportIdListFilter extends FilterDefinitionContract
{
    public function getIdentifier(): string
    {
        return 'skyport_id_list_filter';
    }

    public function getName(): string
    {
        return 'Skyport: ID-Liste (Kontakt / Adressen)';
    }

    public function getDescription(): string
    {
        return 'Prüft ContactReceiverId oder Billing-/Delivery-AddressId gegen eine ID-Liste (Komma/Zeilenumbruch).';
    }

    public function getUIConfigFields(): array
    {
        // Typ (Kontakt / Billing / Delivery)
        $type = pluginApp(SelectboxField::class);
        $type->name = 'type';
        $type->caption = 'Typ';
        $type->value = 'contact';
        $type->selectBoxValues = [
            $this->sbv('contact', 'Kontakt-ID (Empfänger)'),
            $this->sbv('billing', 'ID der Rechnungsadresse'),
            $this->sbv('delivery', 'ID der Lieferadresse'),
        ];

        // Modus (allow / deny)
        $mode = pluginApp(SelectboxField::class);
        $mode->name = 'mode';
        $mode->caption = 'Modus';
        $mode->value = 'allow';
        $mode->selectBoxValues = [
            $this->sbv('allow', 'Zulassen (Treffer = true)'),
            $this->sbv('deny', 'Nicht zulassen (Treffer = false)'),
        ];

        // IDs (Textarea)
        $ids = pluginApp(TextAreaField::class);
        $ids->name = 'ids';
        $ids->caption = 'IDs (Komma oder Zeile – gemischt möglich)';
        $ids->value = '';

        // Optional: Hinweis/Zweck
        $hint = pluginApp(InputField::class);
        $hint->name = 'hint';
        $hint->caption = 'Hinweis / Zweck (optional)';
        $hint->value = '';

        return [
            $type->toArray(),
            $mode->toArray(),
            $ids->toArray(),
            $hint->toArray(),
        ];
    }

    public function getRequiredInputTypes(): array
    {
        // In vielen Plenty-Installationen ist das einfach "order".
        // (Der Filter soll in Order-Flows verfügbar sein.)
        return ['order'];
    }

    public function getOperators(): array
    {
        // Wir steuern Logik über "mode" + IDs, Operatoren brauchen wir nicht.
        // (Falls deine Flow-UI zwingend Operatoren verlangt, sag Bescheid,
        // dann liefern wir hier z.B. ["in"] zurück und werten $filterField["operator"] aus.)
        return [];
    }

    public function getAvailabilities(): array
    {
        // Wenn deine Plenty-Version hier etwas Spezifisches erwartet,
        // passt du das später an (z.B. nur Order-Flows).
        return [];
    }

    public function getCondition(): bool
    {
        // true = ist ein Condition-Filter
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

        // allow: true wenn Treffer
        // deny : true wenn NICHT Treffer
        if ($mode === 'deny') {
            return !$inList;
        }

        return $inList;
    }

    private function sbv(string $value, string $caption)
    {
        $o = pluginApp(SelectboxValue::class);
        $o->value = $value;
        $o->caption = $caption;
        $o->translateCaption = false;
        return $o->toArray();
    }

    private function extractOrder($inputs)
    {
        // typisch: $inputs['order']
        if (is_array($inputs) && isset($inputs['order'])) {
            return $inputs['order'];
        }

        // fallback: falls nur 1 Element übergeben wird
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
        // je nach Flow-Version liegen Config-Felder unterschiedlich:
        // - $filterField['configFields'][$key]
        // - $filterField['config'][$key]
        // - $filterField[$key]
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
        // Komma + Zeilenumbrüche gemischt
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

        // unique ohne array_unique (nur um ganz sicher zu sein)
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
