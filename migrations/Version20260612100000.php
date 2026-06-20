<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260612100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout carliste, heureDebutChargement, heureFinChargement sur Operation';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE operations ADD carliste VARCHAR(100) DEFAULT NULL, ADD heure_debut_chargement VARCHAR(10) DEFAULT NULL, ADD heure_fin_chargement VARCHAR(10) DEFAULT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE operations DROP carliste, DROP heure_debut_chargement, DROP heure_fin_chargement");
    }
}
