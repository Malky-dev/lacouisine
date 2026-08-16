<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260730142447 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add recipe ownership and visibility, then remove the legacy API token.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE recipe ADD created_by_id INT DEFAULT NULL, ADD visibility VARCHAR(255) DEFAULT \'public\' NOT NULL, CHANGE slug slug VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE recipe ADD CONSTRAINT FK_DA88B137B03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id) ON DELETE RESTRICT');
        $this->addSql('CREATE INDEX IDX_DA88B137B03A8386 ON recipe (created_by_id)');
        $this->addSql('ALTER TABLE user DROP api_token');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE recipe DROP FOREIGN KEY FK_DA88B137B03A8386');
        $this->addSql('DROP INDEX IDX_DA88B137B03A8386 ON recipe');
        $this->addSql('ALTER TABLE recipe DROP created_by_id, DROP visibility, CHANGE slug slug VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE user ADD api_token VARCHAR(255) DEFAULT NULL');
    }
}
