<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260108061729 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SEQUENCE safety_alert_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE safety_alert (id INT NOT NULL, user_id INT NOT NULL, group_id INT NOT NULL, location_id INT NOT NULL, type VARCHAR(50) NOT NULL, message TEXT DEFAULT NULL, resolved BOOLEAN DEFAULT false NOT NULL, resolved_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_EBB6D23A76ED395 ON safety_alert (user_id)');
        $this->addSql('CREATE INDEX IDX_EBB6D23FE54D947 ON safety_alert (group_id)');
        $this->addSql('CREATE INDEX IDX_EBB6D2364D218E ON safety_alert (location_id)');
        $this->addSql('CREATE INDEX idx_group_created ON safety_alert (group_id, created_at)');
        $this->addSql('CREATE INDEX idx_group_resolved ON safety_alert (group_id, resolved)');
        $this->addSql('ALTER TABLE safety_alert ADD CONSTRAINT FK_EBB6D23A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE safety_alert ADD CONSTRAINT FK_EBB6D23FE54D947 FOREIGN KEY (group_id) REFERENCES "group" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE safety_alert ADD CONSTRAINT FK_EBB6D2364D218E FOREIGN KEY (location_id) REFERENCES location_history (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP SEQUENCE safety_alert_id_seq CASCADE');
        $this->addSql('ALTER TABLE safety_alert DROP CONSTRAINT FK_EBB6D23A76ED395');
        $this->addSql('ALTER TABLE safety_alert DROP CONSTRAINT FK_EBB6D23FE54D947');
        $this->addSql('ALTER TABLE safety_alert DROP CONSTRAINT FK_EBB6D2364D218E');
        $this->addSql('DROP TABLE safety_alert');
    }
}
