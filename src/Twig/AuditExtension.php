<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class AuditExtension extends AbstractExtension
{
    private const FIELD_LABELS = [
        'companyName' => 'Raison sociale',
        'code' => 'Code',
        'phone' => 'Téléphone',
        'email' => 'Email',
        'address' => 'Adresse',
        'contactName' => 'Contact',
        'isActive' => 'Statut',
        'espece' => 'Espèce',
        'qualite' => 'Qualité',
        'moule' => 'Moule',
        'famille' => 'Famille',
        'nombreCartons' => 'Nombre de cartons',
        'poidsTotal' => 'Poids total',
        'status' => 'Statut',
        'rayon' => 'Rayon',
        'productName' => 'Produit',
        'quantityTons' => 'Quantité (T)',
    ];

    public function getFilters(): array
    {
        return [
            new TwigFilter('audit_readable', [$this, 'formatAudit'], ['is_safe' => ['html']]),
        ];
    }

    public function formatAudit(?array $values, string $action, ?array $oldValues = null): string
    {
        if (!$values && !$oldValues) {
            return '<em class="text-muted">—</em>';
        }

        if ($action === 'update' && $oldValues && $values) {
            return $this->formatUpdate($oldValues, $values);
        }

        if ($action === 'create' && $values) {
            return $this->formatCreate($values);
        }

        if ($action === 'delete' && ($oldValues ?? $values)) {
            $data = $oldValues ?? $values;
            $parts = [];
            foreach ($data as $key => $val) {
                $label = self::FIELD_LABELS[$key] ?? $key;
                $parts[] = "<strong>{$label}</strong> : " . $this->formatValue($val);
            }
            return implode(', ', array_slice($parts, 0, 5));
        }

        // validate/reject
        if ($values) {
            $parts = [];
            foreach ($values as $key => $val) {
                $label = self::FIELD_LABELS[$key] ?? $key;
                $parts[] = "<strong>{$label}</strong> : " . $this->formatValue($val);
            }
            return implode(', ', array_slice($parts, 0, 5));
        }

        return '<em class="text-muted">—</em>';
    }

    private function formatCreate(array $values): string
    {
        $parts = [];
        foreach ($values as $key => $val) {
            if ($val === null || $val === '') continue;
            $label = self::FIELD_LABELS[$key] ?? $key;
            $parts[] = "<strong>{$label}</strong> : " . $this->formatValue($val);
        }
        return implode(', ', array_slice($parts, 0, 6));
    }

    private function formatUpdate(array $old, array $new): string
    {
        $parts = [];
        foreach ($new as $key => $newVal) {
            $oldVal = $old[$key] ?? '—';
            $label = self::FIELD_LABELS[$key] ?? $key;
            $parts[] = "<strong>{$label}</strong> : " . $this->formatValue($oldVal) . ' → ' . $this->formatValue($newVal);
        }
        return implode('<br>', $parts);
    }

    private function formatValue(mixed $val): string
    {
        if (is_bool($val)) return $val ? 'Oui' : 'Non';
        if ($val === null) return '—';
        if (is_array($val)) return json_encode($val);
        return htmlspecialchars((string)$val);
    }
}
