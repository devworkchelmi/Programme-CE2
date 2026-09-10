<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260909070351 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Ajoute Reponse.correctionsOrthographe (verification orthographique des reponses redigees)";
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reponse ADD corrections_orthographe JSON DEFAULT NULL COMMENT \'(DC2Type:json)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reponse DROP corrections_orthographe');
    }
}
