<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260611000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create palette_transfers table if not exists';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            CREATE TABLE IF NOT EXISTS palette_transfers (
                id INT AUTO_INCREMENT NOT NULL,
                palette_id INT NOT NULL,
                from_cold_room_id INT NOT NULL,
                from_rayon VARCHAR(50) DEFAULT NULL,
                to_cold_room_id INT NOT NULL,
                to_rayon VARCHAR(50) DEFAULT NULL,
                transferred_by_id INT NOT NULL,
                transferred_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                INDEX IDX_PT_PALETTE (palette_id),
                INDEX IDX_PT_FROM_ROOM (from_cold_room_id),
                INDEX IDX_PT_TO_ROOM (to_cold_room_id),
                INDEX IDX_PT_USER (transferred_by_id),
                CONSTRAINT FK_PT_PALETTE FOREIGN KEY (palette_id) REFERENCES palettes(id),
                CONSTRAINT FK_PT_FROM FOREIGN KEY (from_cold_room_id) REFERENCES cold_rooms(id),
                CONSTRAINT FK_PT_TO FOREIGN KEY (to_cold_room_id) REFERENCES cold_rooms(id),
                CONSTRAINT FK_PT_USER FOREIGN KEY (transferred_by_id) REFERENCES users(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS palette_transfers');
    }
}