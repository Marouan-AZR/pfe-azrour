<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260507165821 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        // Pour éviter l'erreur SQLite sur l'ajout d'une colonne NOT NULL à des lignes existantes,
        // on la crée d'abord NULL puis on met à jour avec une valeur par défaut.
        // Support SQLite (fallback): ajouter via création de table temporaire
        $this->addSql('CREATE TEMPORARY TABLE __temp__clients AS SELECT id, code, company_name, address, phone, email, is_active, created_at, updated_at FROM clients');
        $this->addSql('DROP TABLE clients');
        $this->addSql("CREATE TABLE clients (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, code VARCHAR(20) NOT NULL, company_name VARCHAR(255) NOT NULL, address CLOB NOT NULL, phone VARCHAR(20) NOT NULL, email VARCHAR(180) NOT NULL, is_active BOOLEAN NOT NULL, numero_agrement VARCHAR(50) NOT NULL DEFAULT 'N/A', created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL)");
        $this->addSql('INSERT INTO clients (id, code, company_name, address, phone, email, is_active, numero_agrement, created_at, updated_at) SELECT id, code, company_name, address, phone, email, is_active, \'N/A\', created_at, updated_at FROM __temp__clients');
        $this->addSql('DROP TABLE __temp__clients');


    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__clients AS SELECT id, code, company_name, address, phone, email, is_active, created_at, updated_at FROM clients');
        $this->addSql('DROP TABLE clients');
        $this->addSql('CREATE TABLE clients (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, code VARCHAR(20) NOT NULL, company_name VARCHAR(255) NOT NULL, address CLOB NOT NULL, phone VARCHAR(20) NOT NULL, email VARCHAR(180) NOT NULL, is_active BOOLEAN NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL)');
        $this->addSql('INSERT INTO clients (id, code, company_name, address, phone, email, is_active, created_at, updated_at) SELECT id, code, company_name, address, phone, email, is_active, created_at, updated_at FROM __temp__clients');
        $this->addSql('DROP TABLE __temp__clients');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_C82E7477153098 ON clients (code)');
    }
}
