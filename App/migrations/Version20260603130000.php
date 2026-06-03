<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Tool context: create tool_permission_grants for per-project "always" grants.
 */
final class Version20260603130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Tool context: create tool_permission_grants table (persisted "always" permissions).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE tool_permission_grants (project_root VARCHAR(512) NOT NULL, type VARCHAR(32) NOT NULL, pattern VARCHAR(512) NOT NULL, PRIMARY KEY (project_root, type, pattern))');
        $this->addSql('CREATE INDEX idx_grant_project ON tool_permission_grants (project_root)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE tool_permission_grants');
    }
}
