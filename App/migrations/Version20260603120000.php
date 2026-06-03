<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Tool context: create tool_todos for the persisted per-session agent todo list.
 */
final class Version20260603120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Tool context: create tool_todos table (per-session agent todo list).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE tool_todos (session_id VARCHAR(64) NOT NULL, position INT NOT NULL, content TEXT NOT NULL, status VARCHAR(16) NOT NULL, priority VARCHAR(16) NOT NULL, PRIMARY KEY (session_id, position))');
        $this->addSql('CREATE INDEX idx_todo_session ON tool_todos (session_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE tool_todos');
    }
}
