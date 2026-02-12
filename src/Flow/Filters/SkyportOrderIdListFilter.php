<?php

namespace SkyportEreignisFilterFlow\Flow\Filters;

use Plenty\Modules\Flow\Filters\Definitions\Contracts\FilterDefinitionContract;

class SkyportOrderIdListFilter implements FilterDefinitionContract
{
    public function getIdentifier(): string
    {
        return 'skyport_order_id_list_filter';
    }

    public function getName(): string
    {
        return 'Skyport: Order IDs Filter (Contact/Address)';
    }

    public function getDescription(): string
    {
        return 'Prüft ContactReceiverId, BillingAddressId oder DeliveryAddressId gegen eine konfigurierbare ID-Liste (Komma und Zeilenumbrüche erlaubt).';
    }

    public function getUIConfigFields(): array
    {
        return [
            [
                'key' => 'type',
                'label' => 'Typ',
                'type' => 'dropdown',
                'possibleValues' => [
                    'contact' => 'Kontakt-ID (Empfänger)',
                    'billing' => 'ID der Rechnungsadresse',
                    'delivery' => 'ID der Lieferadresse'
                ],
                'default' => 'contact'
            ],
            [
                'key' => 'mode',
                'label' => 'Modus',
                'type' => 'dropdown',
                'possibleValues' => [
                    'allow' => 'Zulassen (nur wenn in Liste)',
                    'deny'  => 'Nicht zulassen (nur wenn NICHT in Liste)'
                ],
                'default' => 'allow'
            ],
            [
                'key' => 'ids',
                'label' => 'IDs (eine pro Zeile ODER Komma-getrennt)',
                'type' => 'textarea',
                'default' => ''
            ],
            [
                'key' => 'hint',
                'label' => 'Hinweis / Zweck (optional)',
                'type' => 'text',
                'default' => ''
            ]
        ];
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
        return false;
    }

    public function performFilter($inputs, $filterField, $extraParams = []): bool
    {
        $order = $this->extractOrderFromInputs($inputs);
        if (!$order) {
            return false;
        }

        $type = isset($filterField['type']) ? (string)$filterField['type'] : 'contact';
        $mode = isset($filterField['mode']) ? (string)$filterField['mode'] : 'allow';
        $idsInput = isset($filterField['ids']) ? (string)$filterField['ids'] : '';

        $ids = $this->parseIds($idsInput);
        if (count($ids) === 0) {
            return false;
        }

        $value = 0;

        if ($type === 'contact') {
            $value = $this->getContactReceiverId($order);
        } elseif ($type === 'billing') {
            $value = $this->getBillingAddressId($order);
        } elseif ($type === 'delivery') {
            $value = $this->getDeliveryAddressId($order);
        } else {
            return false;
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

    public function searchCriteria($field = []): string
    {
        return '';
    }

    public function searchCriteriaValue($value, $operator = ''): void
    {
    }

    public function isSystemSpecific(): bool
    {
        return false;
    }

    public function addOperators($configForm, $key = "key")
    {
        return $configForm;
    }

    public function validateConfigFields($configFields): void
    {
    }

    public function validateInputs($inputs): void
    {
    }

    public function mapFilterFields($filterField): void
    {
    }

    private function extractOrderFromInputs($inputs)
    {
        if (!is_array($inputs)) {
            return null;
        }

        if (isset($inputs['order'])) {
            return $inputs['order'];
        }
        if (isset($inputs['Order'])) {
            return $inputs['Order'];
        }
        if (isset($inputs['orderModel'])) {
            return $inputs['orderModel'];
        }
        if (isset($inputs['data']) && is_array($inputs['data']) && isset($inputs['data']['order'])) {
            return $inputs['data']['order'];
        }

        return null;
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
        foreach ($out as $id) {
            if (!in_array($id, $unique, true)) {
                $unique[] = $id;
            }
        }

        return $unique;
    }

    private function getContactReceiverId($order): int
    {
        if (isset($order->contactReceiverId)) {
            $id = (int)$order->contactReceiverId;
            if ($id > 0) {
                return $id;
            }
        }

        if (isset($order->contactId)) {
            $id = (int)$order->contactId;
            if ($id > 0) {
                return $id;
            }
        }

        return 0;
    }

    private function getBillingAddressId($order): int
    {
        if (isset($order->billingAddress) && is_object($order->billingAddress) && isset($order->billingAddress->id)) {
            $id = (int)$order->billingAddress->id;
            if ($id > 0) {
                return $id;
            }
        }

        if (isset($order->billingAddressId)) {
            $id = (int)$order->billingAddressId;
            if ($id > 0) {
                return $id;
            }
        }

        return 0;
    }

    private function getDeliveryAddressId($order): int
    {
        if (isset($order->deliveryAddress) && is_object($order->deliveryAddress) && isset($order->deliveryAddress->id)) {
            $id = (int)$order->deliveryAddress->id;
            if ($id > 0) {
                return $id;
            }
        }

        if (isset($order->deliveryAddressId)) {
            $id = (int)$order->deliveryAddressId;
            if ($id > 0) {
                return $id;
            }
        }

        return 0;
    }
}
