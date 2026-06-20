<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260610130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add sourcePalette + cartonsReellementSortis to palettes; carliste + heure chargement to fiches_decharge';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("SET @db = DATABASE();");

        // palettes.source_palette_id
        $this->addSql("SELECT COUNT(*) INTO @c FROM information_schema.columns WHERE table_schema=@db AND table_name='palettes' AND column_name='source_palette_id'");
        $this->addSql("SET @sql = IF(@c=0, 'ALTER TABLE palettes ADD source_palette_id INT NULL, ADD CONSTRAINT fk_palette_source FOREIGN KEY (source_palette_id) REFERENCES palettes(id) ON DELETE SET NULL', 'SELECT 1');");
        $this->addSql('PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;');

        // palettes.cartons_reellement_sortis
        $this->addSql("SELECT COUNT(*) INTO @c FROM information_schema.columns WHERE table_schema=@db AND table_name='palettes' AND column_name='cartons_reellement_sortis'");
        $this->addSql("SET @sql = IF(@c=0, 'ALTER TABLE palettes ADD cartons_reellement_sortis INT NULL', 'SELECT 1');");
        $this->addSql('PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;');

        // fiches_decharge.carliste
        $this->addSql("SELECT COUNT(*) INTO @c FROM information_schema.columns WHERE table_schema=@db AND table_name='fiches_decharge' AND column_name='carliste'");
        $this->addSql("SET @sql = IF(@c=0, 'ALTER TABLE fiches_decharge ADD carliste VARCHAR(150) NULL', 'SELECT 1');");
        $this->addSql('PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;');

        // fiches_decharge.heure_debut_chargement
        $this->addSql("SELECT COUNT(*) INTO @c FROM information_schema.columns WHERE table_schema=@db AND table_name='fiches_decharge' AND column_name='heure_debut_chargement'");
        $this->addSql("SET @sql = IF(@c=0, 'ALTER TABLE fiches_decharge ADD heure_debut_chargement VARCHAR(10) NULL', 'SELECT 1');");
        $this->addSql('PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;');

        // fiches_decharge.heure_fin_chargement
        $this->addSql("SELECT COUNT(*) INTO @c FROM information_schema.columns WHERE table_schema=@db AND table_name='fiches_decharge' AND column_name='heure_fin_chargement'");
        $this->addSql("SET @sql = IF(@c=0, 'ALTER TABLE fiches_decharge ADD heure_fin_chargement VARCHAR(10) NULL', 'SELECT 1');");
        $this->addSql('PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;');
    }

    public function down(Schema $schema): void {}
}
