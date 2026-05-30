<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260530163804 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Assistant context: add nullable payload_json column on assistant_messages for tool call/result metadata.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE assistant_messages ADD payload_json TEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE assistant_messages DROP payload_json');
    }
}
