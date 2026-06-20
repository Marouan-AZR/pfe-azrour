<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260611000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout du champ type_stockage (forfait/sejour) sur les tables operations et palettes';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE operations ADD type_stockage VARCHAR(20) DEFAULT NULL");
        $this->addSql("ALTER TABLE palettes ADD type_stockage VARCHAR(20) DEFAULT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE operations DROP COLUMN type_stockage");
        $this->addSql("ALTER TABLE palettes DROP COLUMN type_stockage");
    }
}