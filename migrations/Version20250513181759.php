<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250513181759 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE "group" ADD code VARCHAR(6) DEFAULT NULL');
        $groups = $this->connection->fetchAllAssociative('SELECT id FROM "group"');
        foreach ($groups as $group) {
            $code = str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
            $this->addSql('UPDATE "group" SET code = :code WHERE id = :id AND code IS NULL', [
                'code' => $code,
                'id' => $group['id'],
            ]);
        }
        $this->addSql('ALTER TABLE "group" ALTER COLUMN code SET NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6DC044C577153098 ON "group" (code)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP INDEX UNIQ_6DC044C577153098');
        $this->addSql('ALTER TABLE "group" DROP code');
    }
}
