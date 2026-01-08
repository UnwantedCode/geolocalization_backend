<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260108023539 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE device_token ADD user_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE device_token ADD CONSTRAINT FK_99B2415CA76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_99B2415CA76ED395 ON device_token (user_id)');

        // Custom: Add composite index for message queries (group_id, created_at)
        $this->addSql('CREATE INDEX IDX_message_group_created ON message (group_id, created_at)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE device_token DROP CONSTRAINT FK_99B2415CA76ED395');
        $this->addSql('DROP INDEX IDX_99B2415CA76ED395');
        $this->addSql('ALTER TABLE device_token DROP user_id');

        // Custom: Drop composite index for message queries
        $this->addSql('DROP INDEX IDX_message_group_created');
    }
}
