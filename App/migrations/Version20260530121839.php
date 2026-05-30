<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260530121839 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Assistant context: create assistant_sessions and assistant_messages tables.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE assistant_sessions (id VARCHAR(64) NOT NULL, model_name VARCHAR(128) NOT NULL, title VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, archived BOOLEAN DEFAULT false NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE assistant_messages (id VARCHAR(64) NOT NULL, session_id VARCHAR(64) NOT NULL, role VARCHAR(16) NOT NULL, content TEXT NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_msg_session_created ON assistant_messages (session_id, created_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE assistant_messages');
        $this->addSql('DROP TABLE assistant_sessions');
    }
}
