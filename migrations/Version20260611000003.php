<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260611000003 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout table tarif_clients + colonnes quantity_jours/unit_price_display sur invoice_lines';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            CREATE TABLE IF NOT EXISTS tarif_clients (
                id INT AUTO_INCREMENT NOT NULL,
                client_id INT NOT NULL,
                pu_manip_entree DECIMAL(10,5) NOT NULL DEFAULT 0.07000,
                pu_manip_sortie DECIMAL(10,5) NOT NULL DEFAULT 0.07000,
                pu_sejour       DECIMAL(10,5) NOT NULL DEFAULT 0.00970,
                montant_forfait DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                updated_at      DATETIME DEFAULT NULL,
                updated_by_id   INT DEFAULT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_tarif_client (client_id),
                CONSTRAINT fk_tarif_client FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE CASCADE
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
        ");

        $this->addSql("ALTER TABLE invoice_lines ADD quantity_jours INT DEFAULT NULL");
        $this->addSql("ALTER TABLE invoice_lines ADD unit_price_display VARCHAR(30) DEFAULT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DROP TABLE IF EXISTS tarif_clients");
        $this->addSql("ALTER TABLE invoice_lines DROP COLUMN quantity_jours");
        $this->addSql("ALTER TABLE invoice_lines DROP COLUMN unit_price_display");
    }
}