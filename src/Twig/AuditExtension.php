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
        'poidsTotal' => 'Poids total (kg)',
        'poidsCarton' => 'Poids par carton',
        'poidsRestant' => 'Poids restant',
        'cartonsRestants' => 'Cartons restants',
        'status' => 'Statut',
        'rayon' => 'Rayon',
        'coldRoom' => 'Chambre froide',
        'productName' => 'Produit',
        'quantityTons' => 'Quantité (T)',
        'firstName' => 'Prénom',
        'lastName' => 'Nom',
        'roles' => 'Rôles',
        'type' => 'Type',
        'client' => 'Client',
        'controleur' => 'Contrôleur',
        'dateReception' => 'Date de réception',
        'cdLotClient' => 'Code lot client',
        'transporteur' => 'Transporteur',
        'immatriculation' => 'Immatriculation',
        'temperature' => 'Température',
        'numeroConteneur' => 'N° Conteneur',
        'numeroAgrement' => 'N° Agrément',
        'rejectionReason' => 'Motif de rejet',
        'observation' => 'Observation',
        'name' => 'Nom',
        'maxCapacityTons' => 'Capacité max (T)',
        'targetTemperature' => 'Température cible',
    ];

    private const ENTITY_LABELS = [
        'Client' => 'Client',
        'Operation' => 'Opération',
        'Palette' => 'Palette',
        'ColdRoom' => 'Chambre froide',
        'User' => 'Utilisateur',
        'StockEntry' => 'Entrée de stock',
        'StockExit' => 'Sortie de stock',
        'Invoice' => 'Facture',
        'PaletteTransfer' => 'Transfert',
    ];

    private const ACTION_LABELS = [
        'create' => 'créé(e)',
        'update' => 'modifié(e)',
        'delete' => 'supprimé(e)',
        'validate' => 'validé(e)',
        'reject' => 'rejeté(e)',
    ];

    public function getFilters(): array
    {
        return [
            new TwigFilter('audit_readable', [$this, 'formatAudit'], ['is_safe' => ['html']]),
            new TwigFilter('entity_label', [$this, 'entityLabel']),
        ];
    }

    public function entityLabel(string $type): string
    {
        return self::ENTITY_LABELS[$type] ?? $type;
    }

    public function formatAudit(?array $values, string $action, ?array $oldValues = null): string
    {
        if (!$values && !$oldValues) {
            return '<em class="text-muted">Aucun détail disponible</em>';
        }

        if ($action === 'update' && $oldValues && $values) {
            return $this->formatUpdate($oldValues, $values);
        }

        if ($action === 'create' && $values) {
            return $this->formatCreate($values);
        }

        if ($action === 'delete' && ($oldValues ?? $values)) {
            return $this->formatDelete($oldValues ?? $values);
        }

        if ($action === 'validate' && $values) {
            return $this->formatValidate($values);
        }

        if ($action === 'reject' && $values) {
            return $this->formatReject($values);
        }

        // Fallback
        $data = $values ?? $oldValues;
        if ($data) {
            return $this->formatGeneric($data);
        }

        return '<em class="text-muted">—</em>';
    }

    private function formatCreate(array $values): string
    {
        $lines = [];
        foreach ($values as $key => $val) {
            if ($val === null || $val === '' || $val === []) continue;
            $label = self::FIELD_LABELS[$key] ?? ucfirst($key);
            $lines[] = '<div class="audit-line"><i class="bi bi-dot text-success"></i> <strong>' . $label . '</strong> : ' . $this->formatValue($val) . '</div>';
        }
        return implode('', $lines);
    }

    private function formatUpdate(array $old, array $new): string
    {
        $lines = [];
        foreach ($new as $key => $newVal) {
            $oldVal = $old[$key] ?? null;
            if ($oldVal === $newVal) continue;
            $label = self::FIELD_LABELS[$key] ?? ucfirst($key);
            $lines[] = '<div class="audit-line audit-change"><i class="bi bi-pencil-fill text-warning me-1"></i> <strong>' . $label . '</strong> :<br>'
                . '<span class="text-muted text-decoration-line-through">' . $this->formatValue($oldVal) . '</span>'
                . ' → <span class="text-success">' . $this->formatValue($newVal) . '</span></div>';
        }
        return $lines ? implode('', $lines) : '<em class="text-muted">Aucun changement détecté</em>';
    }

    private function formatDelete(array $data): string
    {
        $lines = ['<div class="audit-line text-danger"><i class="bi bi-trash me-1"></i> Élément supprimé :</div>'];
        foreach ($data as $key => $val) {
            if ($val === null || $val === '') continue;
            $label = self::FIELD_LABELS[$key] ?? ucfirst($key);
            $lines[] = '<div class="audit-line"><i class="bi bi-dot"></i> <strong>' . $label . '</strong> : ' . $this->formatValue($val) . '</div>';
        }
        return implode('', array_slice($lines, 0, 8));
    }

    private function formatValidate(array $values): string
    {
        $lines = ['<div class="audit-line text-success"><i class="bi bi-check-circle-fill me-1"></i> Opération validée</div>'];
        foreach ($values as $key => $val) {
            if ($val === null || $val === '') continue;
            $label = self::FIELD_LABELS[$key] ?? ucfirst($key);
            $lines[] = '<div class="audit-line"><i class="bi bi-dot"></i> <strong>' . $label . '</strong> : ' . $this->formatValue($val) . '</div>';
        }
        return implode('', $lines);
    }

    private function formatReject(array $values): string
    {
        $lines = ['<div class="audit-line text-danger"><i class="bi bi-x-circle-fill me-1"></i> Opération rejetée</div>'];
        if (isset($values['rejectionReason'])) {
            $lines[] = '<div class="audit-line"><strong>Motif :</strong> ' . htmlspecialchars($values['rejectionReason']) . '</div>';
        }
        foreach ($values as $key => $val) {
            if ($key === 'rejectionReason' || $val === null || $val === '') continue;
            $label = self::FIELD_LABELS[$key] ?? ucfirst($key);
            $lines[] = '<div class="audit-line"><i class="bi bi-dot"></i> <strong>' . $label . '</strong> : ' . $this->formatValue($val) . '</div>';
        }
        return implode('', $lines);
    }

    private function formatGeneric(array $data): string
    {
        $lines = [];
        foreach ($data as $key => $val) {
            if ($val === null || $val === '') continue;
            $label = self::FIELD_LABELS[$key] ?? ucfirst($key);
            $lines[] = '<div class="audit-line"><i class="bi bi-dot"></i> <strong>' . $label . '</strong> : ' . $this->formatValue($val) . '</div>';
        }
        return implode('', array_slice($lines, 0, 8));
    }

    private function formatValue(mixed $val): string
    {
        if (is_bool($val)) return $val ? '<span class="badge bg-success">Actif</span>' : '<span class="badge bg-secondary">Inactif</span>';
        if ($val === null) return '—';
        if (is_array($val)) {
            if (isset($val[0]) && is_string($val[0])) {
                // Roles array
                return implode(', ', array_map(fn($v) => str_replace('ROLE_', '', $v), $val));
            }
            return htmlspecialchars(json_encode($val, JSON_UNESCAPED_UNICODE));
        }
        $str = (string)$val;
        // Format status values
        if (in_array($str, ['pending', 'validated', 'rejected'])) {
            return match($str) {
                'pending' => '<span class="badge bg-warning">En attente</span>',
                'validated' => '<span class="badge bg-success">Validé</span>',
                'rejected' => '<span class="badge bg-danger">Rejeté</span>',
            };
        }
        if (in_array($str, ['entry', 'exit'])) {
            return $str === 'entry' ? 'Entrée' : 'Sortie';
        }
        return htmlspecialchars($str);
    }
}
