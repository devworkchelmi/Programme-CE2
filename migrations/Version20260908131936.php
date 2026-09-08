<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260908131936 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute Enfant.diagnosticTermine (cf. Product Specification §4)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE enfant ADD diagnostic_termine TINYINT(1) NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE enfant DROP diagnostic_termine');
    }
}
