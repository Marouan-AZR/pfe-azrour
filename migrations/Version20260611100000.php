<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260611100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout colonnes reset_token et reset_token_expires_at sur la table users';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE users ADD reset_token VARCHAR(100) DEFAULT NULL");
        $this->addSql("ALTER TABLE users ADD reset_token_expires_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE users DROP COLUMN reset_token");
        $this->addSql("ALTER TABLE users DROP COLUMN reset_token_expires_at");
    }
}