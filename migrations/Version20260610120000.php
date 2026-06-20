<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add missing columns in clients table for Client entity.
 */
final class Version20260610120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add missing columns nom_interne, nom_commercial, rc, ice to clients';
    }

    public function up(Schema $schema): void
    {
        // MySQL 8.0: add columns if they don't exist (information_schema checks)
        $this->addSql("SET @db = DATABASE();");

        $this->addSql("SELECT COUNT(*) INTO @has_nom_interne FROM information_schema.columns WHERE table_schema=@db AND table_name='clients' AND column_name='nom_interne'");
        $this->addSql('SET @has_nom_interne := COALESCE(@has_nom_interne,0)');
        $this->addSql("SET @sql = IF(@has_nom_interne=0, 'ALTER TABLE clients ADD nom_interne VARCHAR(255) NULL', 'SELECT 1');");
        $this->addSql('PREPARE stmt1 FROM @sql; EXECUTE stmt1; DEALLOCATE PREPARE stmt1;');

        $this->addSql("SELECT COUNT(*) INTO @has_nom_commercial FROM information_schema.columns WHERE table_schema=@db AND table_name='clients' AND column_name='nom_commercial'");
        $this->addSql('SET @has_nom_commercial := COALESCE(@has_nom_commercial,0)');
        $this->addSql("SET @sql = IF(@has_nom_commercial=0, 'ALTER TABLE clients ADD nom_commercial VARCHAR(255) NULL', 'SELECT 1');");
        $this->addSql('PREPARE stmt2 FROM @sql; EXECUTE stmt2; DEALLOCATE PREPARE stmt2;');

        $this->addSql("SELECT COUNT(*) INTO @has_rc FROM information_schema.columns WHERE table_schema=@db AND table_name='clients' AND column_name='rc'");
        $this->addSql('SET @has_rc := COALESCE(@has_rc,0)');
        $this->addSql("SET @sql = IF(@has_rc=0, 'ALTER TABLE clients ADD rc VARCHAR(100) NULL', 'SELECT 1');");
        $this->addSql('PREPARE stmt3 FROM @sql; EXECUTE stmt3; DEALLOCATE PREPARE stmt3;');

        $this->addSql("SELECT COUNT(*) INTO @has_ice FROM information_schema.columns WHERE table_schema=@db AND table_name='clients' AND column_name='ice'");
        $this->addSql('SET @has_ice := COALESCE(@has_ice,0)');
        $this->addSql("SET @sql = IF(@has_ice=0, 'ALTER TABLE clients ADD ice VARCHAR(100) NULL', 'SELECT 1');");
        $this->addSql('PREPARE stmt4 FROM @sql; EXECUTE stmt4; DEALLOCATE PREPARE stmt4;');
    }

    public function down(Schema $schema): void
    {
        // No-op: safe rollback would require data loss (dropping columns).
    }
}

