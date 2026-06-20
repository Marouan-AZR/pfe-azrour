<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260614224645 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Schema sync: type_stockage length, tarif_clients index rename, users datetime fix';
    }

    public function up(Schema $schema): void
    {
        // type_stockage: VARCHAR(20) → VARCHAR(255)
        $this->addSql('ALTER TABLE operations CHANGE type_stockage type_stockage VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE palettes CHANGE type_stockage type_stockage VARCHAR(255) DEFAULT NULL');

        // Rename uniq_tarif_client → UNIQ_73A95C3119EB6921
        // Must drop FK using the index first, then drop index, recreate index, re-add FK
        $this->addSql('ALTER TABLE tarif_clients DROP FOREIGN KEY FK_73A95C3119EB6921');
        $this->addSql('DROP INDEX uniq_tarif_client ON tarif_clients');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_73A95C3119EB6921 ON tarif_clients (client_id)');
        $this->addSql('ALTER TABLE tarif_clients ADD CONSTRAINT FK_73A95C3119EB6921 FOREIGN KEY (client_id) REFERENCES clients (id)');

        // Remove COMMENT annotation from datetime_immutable column
        $this->addSql('ALTER TABLE users CHANGE reset_token_expires_at reset_token_expires_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE operations CHANGE type_stockage type_stockage VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE palettes CHANGE type_stockage type_stockage VARCHAR(20) DEFAULT NULL');

        $this->addSql('ALTER TABLE tarif_clients DROP FOREIGN KEY FK_73A95C3119EB6921');
        $this->addSql('DROP INDEX UNIQ_73A95C3119EB6921 ON tarif_clients');
        $this->addSql('CREATE UNIQUE INDEX uniq_tarif_client ON tarif_clients (client_id)');
        $this->addSql('ALTER TABLE tarif_clients ADD CONSTRAINT FK_73A95C3119EB6921 FOREIGN KEY (client_id) REFERENCES clients (id)');

        $this->addSql('ALTER TABLE users CHANGE reset_token_expires_at reset_token_expires_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }
}
