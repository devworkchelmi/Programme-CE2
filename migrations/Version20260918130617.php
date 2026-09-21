<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260918130617 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute la partie Orthographe : Enfant.niveauOrthographe, ExerciceOrthographe, AjustementNiveauOrthographe';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE enfant ADD niveau_orthographe INT NOT NULL DEFAULT 1');

        $this->addSql('CREATE TABLE exercice_orthographe (id INT AUTO_INCREMENT NOT NULL, enfant_id INT NOT NULL, type VARCHAR(255) NOT NULL, regle VARCHAR(100) NOT NULL, niveau_vise INT NOT NULL, contenu JSON NOT NULL COMMENT \'(DC2Type:json)\', reponse_donnee JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', correcte VARCHAR(255) DEFAULT NULL, date_creation DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', date_reponse DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_EXERCICE_ORTHOGRAPHE_ENFANT (enfant_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE ajustement_niveau_orthographe (id INT AUTO_INCREMENT NOT NULL, enfant_id INT NOT NULL, niveau_avant INT NOT NULL, niveau_apres INT NOT NULL, regle_appliquee VARCHAR(50) NOT NULL, date_creation DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_AJUSTEMENT_NIVEAU_ORTHOGRAPHE_ENFANT (enfant_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE exercice_orthographe ADD CONSTRAINT FK_EXERCICE_ORTHOGRAPHE_ENFANT FOREIGN KEY (enfant_id) REFERENCES enfant (id)');
        $this->addSql('ALTER TABLE ajustement_niveau_orthographe ADD CONSTRAINT FK_AJUSTEMENT_NIVEAU_ORTHOGRAPHE_ENFANT FOREIGN KEY (enfant_id) REFERENCES enfant (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE exercice_orthographe DROP FOREIGN KEY FK_EXERCICE_ORTHOGRAPHE_ENFANT');
        $this->addSql('ALTER TABLE ajustement_niveau_orthographe DROP FOREIGN KEY FK_AJUSTEMENT_NIVEAU_ORTHOGRAPHE_ENFANT');

        $this->addSql('DROP TABLE exercice_orthographe');
        $this->addSql('DROP TABLE ajustement_niveau_orthographe');

        $this->addSql('ALTER TABLE enfant DROP niveau_orthographe');
    }
}
