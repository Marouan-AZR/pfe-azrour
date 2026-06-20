<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class AuditExtension extends AbstractExtension
{
    private const FIELD_LABELS = [
        'companyName'            => 'Raison sociale',
        'code'                   => 'Code',
        'numero'                 => 'Numéro',
        'phone'                  => 'Téléphone',
        'email'                  => 'Email',
        'address'                => 'Adresse',
        'contactName'            => 'Contact',
        'isActive'               => 'Statut',
        'espece'                 => 'Espèce',
        'qualite'                => 'Qualité',
        'moule'                  => 'Moule',
        'famille'                => 'Famille',
        'nombreCartons'          => 'Nombre de cartons',
        'poidsTotal'             => 'Poids total',
        'poidsCarton'            => 'Poids / carton',
        'poidsRestant'           => 'Poids restant',
        'cartonsRestants'        => 'Cartons restants',
        'status'                 => 'Statut',
        'rayon'                  => 'Rayon',
        'coldRoom'               => 'Chambre froide',
        'productName'            => 'Produit',
        'quantityTons'           => 'Quantité',
        'firstName'              => 'Prénom',
        'lastName'               => 'Nom',
        'roles'                  => 'Rôles',
        'type'                   => 'Type',
        'client'                 => 'Client',
        'controleur'             => 'Contrôleur',
        'validatedBy'            => 'Validé par',
        'validatedAt'            => 'Date de validation',
        'rejectedBy'             => 'Rejeté par',
        'rejectedAt'             => 'Date de rejet',
        'dateReception'          => 'Date de réception',
        'cdLotClient'            => 'Code lot client',
        'transporteur'           => 'Transporteur',
        'immatriculation'        => 'Immatriculation',
        'matricule'              => 'Matricule',
        'temperature'            => 'Température',
        'numeroConteneur'        => 'N° Conteneur',
        'numeroAgrement'         => 'N° Agrément',
        'carliste'               => 'Carliste',
        'heureDebutChargement'   => 'Heure début chargement',
        'heureFinChargement'     => 'Heure fin chargement',
        'rejectionReason'        => 'Motif de rejet',
        'commentaireRejet'       => 'Commentaire de rejet',
        'observation'            => 'Observation',
        'name'                   => 'Nom',
        'maxCapacityTons'        => 'Capacité max (T)',
        'targetTemperature'      => 'Température cible',
        'operation'              => 'Opération',
        'createdAt'              => 'Créé le',
        'updatedAt'              => 'Modifié le',
    ];

    /** Fields that are internal/technical and should not be shown in the feed */
    private const HIDDEN_FIELDS = ['id', 'updatedAt', 'createdAt'];

    private const ENTITY_LABELS = [
        'Client'          => 'Client',
        'Operation'       => 'Opération',
        'Palette'         => 'Palette',
        'ColdRoom'        => 'Chambre froide',
        'User'            => 'Utilisateur',
        'StockEntry'      => 'Entrée de stock',
        'StockExit'       => 'Sortie de stock',
        'Invoice'         => 'Facture',
        'PaletteTransfer' => 'Transfert palette',
        'FicheDecharge'   => 'Fiche de décharge',
    ];

    private const ENTITY_ICONS = [
        'Client'          => 'bi-building',
        'Operation'       => 'bi-box-seam',
        'Palette'         => 'bi-layers-fill',
        'ColdRoom'        => 'bi-thermometer-snow',
        'User'            => 'bi-person-gear',
        'StockEntry'      => 'bi-box-arrow-in-down',
        'StockExit'       => 'bi-box-arrow-up',
        'Invoice'         => 'bi-receipt',
        'PaletteTransfer' => 'bi-arrow-left-right',
        'FicheDecharge'   => 'bi-file-earmark-text',
    ];

    private const ACTION_LABELS = [
        'create'   => 'créé(e)',
        'update'   => 'modifié(e)',
        'delete'   => 'supprimé(e)',
        'validate' => 'validé(e)',
        'reject'   => 'rejeté(e)',
    ];

    private const WEIGHT_FIELDS = ['poidsTotal', 'poidsCarton', 'poidsRestant'];

    private const ACTION_COLORS = [
        'create'   => '#10b981',
        'update'   => '#f59e0b',
        'delete'   => '#ef4444',
        'validate' => '#6366f1',
        'reject'   => '#64748b',
        'other'    => '#0ea5e9',
    ];

    public function getFilters(): array
    {
        return [
            new TwigFilter('audit_readable', [$this, 'formatAudit'],  ['is_safe' => ['html']]),
            new TwigFilter('audit_summary',  [$this, 'getSummary'],   ['is_safe' => ['html']]),
            new TwigFilter('audit_card',     [$this, 'formatCard'],   ['is_safe' => ['html']]),
            new TwigFilter('entity_label',   [$this, 'entityLabel']),
        ];
    }

    public function entityLabel(string $type): string
    {
        return self::ENTITY_LABELS[$type] ?? $type;
    }

    /**
     * Returns a short one-line summary for display in the table row.
     */
    public function getSummary(?array $newValues, ?array $oldValues, string $action, string $entityType): string
    {
        $entityLabel = self::ENTITY_LABELS[$entityType] ?? $entityType;
        $actionLabel = self::ACTION_LABELS[$action] ?? $action;
        $values      = $newValues ?? $oldValues;
        $identifier  = $this->getIdentifier($values, $entityType);

        if ($action === 'update' && $newValues && $oldValues) {
            $changes = count(array_filter(
                array_keys($newValues),
                fn($k) => ($oldValues[$k] ?? null) !== $newValues[$k]
            ));
            $countBadge = $changes > 0
                ? ' <span class="audit-count-badge">' . $changes . ' champ' . ($changes > 1 ? 's' : '') . '</span>'
                : '';
            return $entityLabel . ' ' . $actionLabel . $countBadge
                . ($identifier ? ' — <em>' . htmlspecialchars((string)$identifier) . '</em>' : '');
        }

        return $entityLabel . ' ' . $actionLabel
            . ($identifier ? ' — <em>' . htmlspecialchars((string)$identifier) . '</em>' : '');
    }

    /**
     * Returns full human-readable HTML for the audit modal.
     */
    public function formatAudit(?array $values, string $action, ?array $oldValues = null, string $entityType = ''): string
    {
        if (!$values && !$oldValues) {
            return '<em class="text-muted">Aucun détail disponible</em>';
        }

        return match ($action) {
            'update'   => ($oldValues && $values)
                            ? $this->formatUpdate($oldValues, $values, $entityType)
                            : $this->formatGeneric($values ?? $oldValues ?? [], $entityType),
            'create'   => $values ? $this->formatCreate($values, $entityType) : '<em class="text-muted">—</em>',
            'delete'   => $this->formatDelete($oldValues ?? $values ?? [], $entityType),
            'validate' => $values ? $this->formatValidate($values, $entityType) : '<em class="text-muted">—</em>',
            'reject'   => $values ? $this->formatReject($values, $entityType) : '<em class="text-muted">—</em>',
            default    => $this->formatGeneric($values ?? $oldValues ?? [], $entityType),
        };
    }

    // ── Action-specific formatters ────────────────────────────────────────

    private function formatCreate(array $values, string $entityType): string
    {
        $entityLabel = self::ENTITY_LABELS[$entityType] ?? $entityType;
        $icon        = self::ENTITY_ICONS[$entityType] ?? 'bi-plus-circle-fill';
        $identifier  = $this->getIdentifier($values, $entityType);
        $title       = $entityLabel . ' créé' . ($identifier ? ' : ' . htmlspecialchars((string)$identifier) : '');

        $html  = $this->blockHeader('create', $icon, $title);
        $html .= '<ul class="audit-field-list">';
        foreach ($values as $key => $val) {
            if ($val === null || $val === '' || $val === [] || $key === 'id') {
                continue;
            }
            $html .= $this->fieldItem($key, $val);
        }
        $html .= '</ul></div>';

        return $html;
    }

    private function formatUpdate(array $old, array $new, string $entityType): string
    {
        $entityLabel = self::ENTITY_LABELS[$entityType] ?? $entityType;
        $icon        = self::ENTITY_ICONS[$entityType] ?? 'bi-pencil-fill';
        $identifier  = $this->getIdentifier($new, $entityType) ?? $this->getIdentifier($old, $entityType);
        $title       = $entityLabel . ' modifié' . ($identifier ? ' : ' . htmlspecialchars((string)$identifier) : '');

        $changes = [];
        foreach ($new as $key => $newVal) {
            $oldVal = $old[$key] ?? null;
            if ($oldVal !== $newVal) {
                $changes[$key] = ['old' => $oldVal, 'new' => $newVal];
            }
        }

        if (!$changes) {
            return '<em class="text-muted">Aucun changement détecté</em>';
        }

        $html  = $this->blockHeader('update', $icon, $title);
        $html .= '<ul class="audit-field-list">';
        foreach ($changes as $key => $c) {
            $label = self::FIELD_LABELS[$key] ?? ucfirst($key);
            $html .= '<li class="audit-field-item audit-change-item">';
            $html .= '<span class="audit-field-label">'
                . '<i class="bi bi-pencil-fill me-1" style="color:#f59e0b;font-size:.65rem"></i>'
                . htmlspecialchars($label)
                . '</span>';
            $html .= '<div class="audit-diff">'
                . '<span class="audit-old-val">' . $this->formatValueByKey($c['old'], $key) . '</span>'
                . '<i class="bi bi-arrow-right-short audit-diff-arrow"></i>'
                . '<span class="audit-new-val">' . $this->formatValueByKey($c['new'], $key) . '</span>'
                . '</div>';
            $html .= '</li>';
        }
        $html .= '</ul></div>';

        return $html;
    }

    private function formatDelete(array $data, string $entityType): string
    {
        $entityLabel = self::ENTITY_LABELS[$entityType] ?? $entityType;
        $icon        = self::ENTITY_ICONS[$entityType] ?? 'bi-trash-fill';
        $identifier  = $this->getIdentifier($data, $entityType);
        $title       = $entityLabel . ' supprimé' . ($identifier ? ' : ' . htmlspecialchars((string)$identifier) : '');

        $html  = $this->blockHeader('delete', $icon, $title);
        $html .= '<ul class="audit-field-list">';
        foreach (array_slice($data, 0, 10) as $key => $val) {
            if ($val === null || $val === '' || $key === 'id') {
                continue;
            }
            $html .= $this->fieldItem($key, $val);
        }
        $html .= '</ul></div>';

        return $html;
    }

    private function formatValidate(array $values, string $entityType): string
    {
        $entityLabel = self::ENTITY_LABELS[$entityType] ?? $entityType;
        $icon        = self::ENTITY_ICONS[$entityType] ?? 'bi-check-circle-fill';
        $identifier  = $this->getIdentifier($values, $entityType);
        $title       = $entityLabel . ' validé' . ($identifier ? ' : ' . htmlspecialchars((string)$identifier) : '');

        $html  = $this->blockHeader('validate', $icon, $title);
        $html .= '<ul class="audit-field-list">';
        foreach ($values as $key => $val) {
            if ($val === null || $val === '' || $key === 'id') {
                continue;
            }
            $html .= $this->fieldItem($key, $val);
        }
        $html .= '</ul></div>';

        return $html;
    }

    private function formatReject(array $values, string $entityType): string
    {
        $entityLabel = self::ENTITY_LABELS[$entityType] ?? $entityType;
        $icon        = self::ENTITY_ICONS[$entityType] ?? 'bi-x-circle-fill';
        $identifier  = $this->getIdentifier($values, $entityType);
        $title       = $entityLabel . ' rejeté' . ($identifier ? ' : ' . htmlspecialchars((string)$identifier) : '');

        $html  = $this->blockHeader('reject', $icon, $title);
        $html .= '<ul class="audit-field-list">';

        if (isset($values['rejectionReason'])) {
            $html .= '<li class="audit-field-item audit-reason-item">'
                . '<span class="audit-field-label"><i class="bi bi-chat-left-text-fill me-1"></i>Motif de rejet</span>'
                . '<span class="audit-field-value text-danger fw-semibold">' . htmlspecialchars((string)$values['rejectionReason']) . '</span>'
                . '</li>';
        }

        foreach ($values as $key => $val) {
            if ($key === 'rejectionReason' || $val === null || $val === '' || $key === 'id') {
                continue;
            }
            $html .= $this->fieldItem($key, $val);
        }
        $html .= '</ul></div>';

        return $html;
    }

    private function formatGeneric(array $data, string $entityType): string
    {
        $entityLabel = self::ENTITY_LABELS[$entityType] ?? $entityType;
        $icon        = self::ENTITY_ICONS[$entityType] ?? 'bi-info-circle-fill';
        $identifier  = $this->getIdentifier($data, $entityType);
        $title       = $entityLabel . ($identifier ? ' : ' . htmlspecialchars((string)$identifier) : '');

        $html  = $this->blockHeader('other', $icon, $title);
        $html .= '<ul class="audit-field-list">';
        foreach (array_slice($data, 0, 10) as $key => $val) {
            if ($val === null || $val === '' || $key === 'id') {
                continue;
            }
            $html .= $this->fieldItem($key, $val);
        }
        $html .= '</ul></div>';

        return $html;
    }

    // ── Card format (flat, for feed display) ─────────────────────────────

    public function formatCard(?array $values, string $action, ?array $oldValues = null, string $entityType = ''): string
    {
        if (!$values && !$oldValues) {
            return '';
        }

        return match ($action) {
            'create'   => $this->cardCreate($values ?? [], $entityType),
            'update'   => ($oldValues && $values) ? $this->cardUpdate($oldValues, $values, $entityType) : $this->cardSimple($values ?? $oldValues ?? []),
            'delete'   => $this->cardSimple($oldValues ?? $values ?? []),
            'validate' => $this->cardSimple($values ?? []),
            'reject'   => $this->cardReject($values ?? []),
            default    => $this->cardSimple($values ?? $oldValues ?? []),
        };
    }

    private function cardCreate(array $values, string $entityType): string
    {
        $entityLabel = self::ENTITY_LABELS[$entityType] ?? $entityType;
        $identifier  = $this->getIdentifier($values, $entityType);

        $html = '<div class="acd">';
        if ($identifier) {
            $html .= '<div class="acd-row acd-lead"><strong>' . $entityLabel . ' créé :</strong> ' . htmlspecialchars((string)$identifier) . '</div>';
        }
        foreach ($values as $key => $val) {
            if ($val === null || $val === '' || $val === [] || \in_array($key, self::HIDDEN_FIELDS, true)) continue;
            $label = self::FIELD_LABELS[$key] ?? $this->labelFromKey($key);
            $html .= '<div class="acd-row"><strong>' . htmlspecialchars($label) . ' :</strong> ' . $this->formatValueByKey($val, $key) . '</div>';
        }
        $html .= '</div>';
        return $html;
    }

    private function cardUpdate(array $old, array $new, string $entityType): string
    {
        $changes = [];
        foreach ($new as $key => $newVal) {
            if (\in_array($key, self::HIDDEN_FIELDS, true)) continue;
            $oldVal = $old[$key] ?? null;
            if ($oldVal !== $newVal) {
                $changes[$key] = ['old' => $oldVal, 'new' => $newVal];
            }
        }

        if (!$changes) {
            return '<div class="acd"><div class="acd-row text-muted small">Aucun changement détecté</div></div>';
        }

        $html = '<div class="acd">';
        foreach ($changes as $key => $c) {
            $label = self::FIELD_LABELS[$key] ?? $this->labelFromKey($key);
            $html .= '<div class="acd-row"><strong>' . htmlspecialchars($label) . ' modifié :</strong></div>';
            $html .= '<div class="acd-diff">'
                . 'Ancien : <span class="acd-old">' . $this->formatValueByKey($c['old'], $key) . '</span>'
                . ' → Nouveau : <span class="acd-new">' . $this->formatValueByKey($c['new'], $key) . '</span>'
                . '</div>';
        }
        $html .= '</div>';
        return $html;
    }

    private function cardReject(array $values): string
    {
        $html = '<div class="acd">';
        if (isset($values['rejectionReason'])) {
            $html .= '<div class="acd-row"><strong>Motif de rejet :</strong> <span class="acd-reject">' . htmlspecialchars((string)$values['rejectionReason']) . '</span></div>';
        }
        if (isset($values['commentaireRejet'])) {
            $html .= '<div class="acd-row"><strong>Commentaire :</strong> ' . htmlspecialchars((string)$values['commentaireRejet']) . '</div>';
        }
        foreach ($values as $key => $val) {
            if (\in_array($key, ['rejectionReason', 'commentaireRejet'], true) || $val === null || $val === '' || \in_array($key, self::HIDDEN_FIELDS, true)) continue;
            $label = self::FIELD_LABELS[$key] ?? $this->labelFromKey($key);
            $html .= '<div class="acd-row"><strong>' . htmlspecialchars($label) . ' :</strong> ' . $this->formatValueByKey($val, $key) . '</div>';
        }
        $html .= '</div>';
        return $html;
    }

    private function cardSimple(array $values): string
    {
        $html  = '<div class="acd">';
        $count = 0;
        foreach ($values as $key => $val) {
            if ($val === null || $val === '' || \in_array($key, self::HIDDEN_FIELDS, true)) continue;
            if ($count >= 8) break;
            $label = self::FIELD_LABELS[$key] ?? $this->labelFromKey($key);
            $html .= '<div class="acd-row"><strong>' . htmlspecialchars($label) . ' :</strong> ' . $this->formatValueByKey($val, $key) . '</div>';
            $count++;
        }
        $html .= '</div>';
        return $html;
    }

    /** Converts camelCase key to readable label when not in FIELD_LABELS */
    private function labelFromKey(string $key): string
    {
        return ucfirst(trim(preg_replace('/([A-Z])/', ' $1', $key) ?? $key));
    }

    // ── Internal helpers ──────────────────────────────────────────────────

    private function blockHeader(string $actionType, string $icon, string $title): string
    {
        $color = self::ACTION_COLORS[$actionType] ?? self::ACTION_COLORS['other'];

        return '<div class="audit-block">'
            . '<div class="audit-block-header" style="border-left:3px solid ' . $color . ';color:' . $color . '">'
            . '<i class="bi ' . $icon . '"></i>'
            . '<strong>' . $title . '</strong>'
            . '</div>';
    }

    private function fieldItem(string $key, mixed $val): string
    {
        $label = self::FIELD_LABELS[$key] ?? ucfirst($key);

        return '<li class="audit-field-item">'
            . '<span class="audit-field-label">' . htmlspecialchars($label) . '</span>'
            . '<span class="audit-field-value">' . $this->formatValueByKey($val, $key) . '</span>'
            . '</li>';
    }

    private function getIdentifier(?array $values, string $entityType): mixed
    {
        if (!$values) {
            return null;
        }

        return match ($entityType) {
            'Client'          => $values['companyName'] ?? null,
            'User'            => trim(($values['firstName'] ?? '') . ' ' . ($values['lastName'] ?? '')) ?: null,
            'Operation'       => $values['code'] ?? null,
            'Palette'         => $values['code'] ?? ($values['espece'] ?? null),
            'ColdRoom'        => $values['name'] ?? null,
            'StockEntry',
            'StockExit'       => $values['productName'] ?? ($values['code'] ?? null),
            'Invoice'         => $values['number'] ?? ($values['code'] ?? null),
            'PaletteTransfer' => isset($values['palette']) ? 'Palette #' . $values['palette'] : null,
            default           => $values['name'] ?? ($values['code'] ?? ($values['companyName'] ?? null)),
        };
    }

    private function formatValueByKey(mixed $val, string $key): string
    {
        // Weight fields stored in tonnes → show kg + T
        if (is_numeric($val) && in_array($key, self::WEIGHT_FIELDS, true)) {
            $tonnes = (float)$val;
            $kg     = (int)round($tonnes * 1000);
            return number_format($kg, 0, ',', ' ') . ' kg'
                . ' <small class="text-muted">(' . number_format($tonnes, 2, ',', ' ') . ' T)</small>';
        }

        // quantityTons stored in tonnes
        if ($key === 'quantityTons' && is_numeric($val)) {
            $t  = (float)$val;
            $kg = (int)round($t * 1000);
            return number_format($t, 3, ',', ' ') . ' T'
                . ' <small class="text-muted">(' . number_format($kg, 0, ',', ' ') . ' kg)</small>';
        }

        return $this->formatValue($val);
    }

    private function formatValue(mixed $val): string
    {
        if (is_bool($val)) {
            return $val
                ? '<span class="badge bg-success">Actif</span>'
                : '<span class="badge bg-secondary">Inactif</span>';
        }

        if ($val === null) {
            return '<span class="text-muted">—</span>';
        }

        if (is_array($val)) {
            if (isset($val[0]) && is_string($val[0])) {
                // Roles array
                return implode(' ', array_map(
                    fn($v) => '<span class="badge bg-primary bg-opacity-25 text-primary">'
                        . htmlspecialchars(str_replace('ROLE_', '', $v))
                        . '</span>',
                    $val
                ));
            }
            return '<code class="text-muted small">' . htmlspecialchars(json_encode($val, JSON_UNESCAPED_UNICODE)) . '</code>';
        }

        $str = (string)$val;

        // Doctrine object reference: "App\Entity\User#1" → "Utilisateur #1"
        if (preg_match('/App\\\\Entity\\\\(\w+)#(\d+)/', $str, $m)) {
            $entityName = self::ENTITY_LABELS[$m[1]] ?? $m[1];
            return '<span class="text-muted">' . htmlspecialchars($entityName) . ' #' . $m[2] . '</span>';
        }

        // ISO date strings
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $str)) {
            try {
                $dt = new \DateTime($str);
                return '<span class="text-muted">' . $dt->format('d/m/Y à H:i') . '</span>';
            } catch (\Exception) {}
        }

        // Status badges (StockStatus)
        $statusMap = [
            'pending'               => ['bg-warning text-dark',  'En attente'],
            'en_attente_controle'   => ['bg-warning text-dark',  'En attente de contrôle'],
            'en_attente_validation' => ['bg-info text-dark',     'En attente de validation'],
            'validated'             => ['bg-success',            'Validé'],
            'rejected'              => ['bg-danger',             'Rejeté'],
            // FicheStatus
            'en_cours_controle'     => ['bg-info text-dark',     'En cours de contrôle'],
            'validee'               => ['bg-success',            'Validée'],
            'rejetee'               => ['bg-danger',             'Rejetée'],
        ];
        if (isset($statusMap[$str])) {
            [$cls, $label] = $statusMap[$str];
            return '<span class="badge ' . $cls . '">' . $label . '</span>';
        }

        // Operation type
        if ($str === 'entry') {
            return '<span class="badge bg-success">Entrée</span>';
        }
        if ($str === 'exit') {
            return '<span class="badge bg-danger">Sortie</span>';
        }

        return htmlspecialchars($str);
    }
}
