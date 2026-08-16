<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251115153738 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Allow nullable recipe slugs and add legacy API tokens.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE recipe CHANGE slug slug VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE user ADD api_token VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE recipe CHANGE slug slug VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE user DROP api_token');
    }
}
