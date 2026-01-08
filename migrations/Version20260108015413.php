<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260108015413 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add owner_user_id column to groups table and set existing groups\' owners to first member';
    }

    public function up(Schema $schema): void
    {
        // Add column as nullable first for existing data
        $this->addSql('ALTER TABLE "group" ADD owner_user_id INT DEFAULT NULL');

        // Set owner to first member from user_group for existing groups
        $this->addSql('
            UPDATE "group" g
            SET owner_user_id = (
                SELECT ug.user_id
                FROM user_group ug
                WHERE ug.group_id = g.id
                ORDER BY ug.user_id ASC
                LIMIT 1
            )
            WHERE EXISTS (SELECT 1 FROM user_group ug WHERE ug.group_id = g.id)
        ');

        // Make column NOT NULL after data migration
        $this->addSql('ALTER TABLE "group" ALTER COLUMN owner_user_id SET NOT NULL');

        // Add foreign key constraint and index
        $this->addSql('ALTER TABLE "group" ADD CONSTRAINT FK_6DC044C52B18554A FOREIGN KEY (owner_user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_6DC044C52B18554A ON "group" (owner_user_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE "group" DROP CONSTRAINT FK_6DC044C52B18554A');
        $this->addSql('DROP INDEX IDX_6DC044C52B18554A');
        $this->addSql('ALTER TABLE "group" DROP owner_user_id');
    }
}
