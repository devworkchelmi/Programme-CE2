<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260908115016 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE ajustement_niveau (id INT AUTO_INCREMENT NOT NULL, session_id INT NOT NULL, niveau_avant INT NOT NULL, niveau_apres INT NOT NULL, regle_appliquee VARCHAR(50) NOT NULL, date_creation DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_A682BF29613FECDF (session_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE enfant (id INT AUTO_INCREMENT NOT NULL, prenom_affichage VARCHAR(100) NOT NULL, niveau_actuel INT NOT NULL, date_creation DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE feedback (id INT AUTO_INCREMENT NOT NULL, reponse_id INT NOT NULL, texte_feedback LONGTEXT NOT NULL, UNIQUE INDEX UNIQ_D2294458CF18BB82 (reponse_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE question (id INT AUTO_INCREMENT NOT NULL, texte_genere_id INT NOT NULL, type VARCHAR(255) NOT NULL, enonce LONGTEXT NOT NULL, choix JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', reponse_attendue_ou_criteres LONGTEXT NOT NULL, INDEX IDX_B6F7494EC8F9ECEE (texte_genere_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE reponse (id INT AUTO_INCREMENT NOT NULL, question_id INT NOT NULL, contenu_enfant LONGTEXT NOT NULL, correcte VARCHAR(255) DEFAULT NULL, type_erreur_propose VARCHAR(255) DEFAULT NULL, type_erreur_confirme VARCHAR(255) DEFAULT NULL, commentaire_llm LONGTEXT DEFAULT NULL, UNIQUE INDEX UNIQ_5FB6DEC71E27F6BF (question_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE session (id INT AUTO_INCREMENT NOT NULL, enfant_id INT NOT NULL, date DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', type VARCHAR(255) NOT NULL, niveau_vise INT NOT NULL, statut VARCHAR(255) NOT NULL, INDEX IDX_D044D5D4450D2529 (enfant_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE texte_genere (id INT AUTO_INCREMENT NOT NULL, session_id INT NOT NULL, titre VARCHAR(255) NOT NULL, contenu LONGTEXT NOT NULL, niveau_vocabulaire_vise INT NOT NULL, longueur_moyenne_phrase DOUBLE PRECISION DEFAULT NULL, statut_relecture VARCHAR(255) NOT NULL, relu_par VARCHAR(100) DEFAULT NULL, date_relecture DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_894AE62D613FECDF (session_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL COMMENT \'(DC2Type:json)\', password VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_8D93D649E7927C74 (email), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE ajustement_niveau ADD CONSTRAINT FK_A682BF29613FECDF FOREIGN KEY (session_id) REFERENCES session (id)');
        $this->addSql('ALTER TABLE feedback ADD CONSTRAINT FK_D2294458CF18BB82 FOREIGN KEY (reponse_id) REFERENCES reponse (id)');
        $this->addSql('ALTER TABLE question ADD CONSTRAINT FK_B6F7494EC8F9ECEE FOREIGN KEY (texte_genere_id) REFERENCES texte_genere (id)');
        $this->addSql('ALTER TABLE reponse ADD CONSTRAINT FK_5FB6DEC71E27F6BF FOREIGN KEY (question_id) REFERENCES question (id)');
        $this->addSql('ALTER TABLE session ADD CONSTRAINT FK_D044D5D4450D2529 FOREIGN KEY (enfant_id) REFERENCES enfant (id)');
        $this->addSql('ALTER TABLE texte_genere ADD CONSTRAINT FK_894AE62D613FECDF FOREIGN KEY (session_id) REFERENCES session (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE ajustement_niveau DROP FOREIGN KEY FK_A682BF29613FECDF');
        $this->addSql('ALTER TABLE feedback DROP FOREIGN KEY FK_D2294458CF18BB82');
        $this->addSql('ALTER TABLE question DROP FOREIGN KEY FK_B6F7494EC8F9ECEE');
        $this->addSql('ALTER TABLE reponse DROP FOREIGN KEY FK_5FB6DEC71E27F6BF');
        $this->addSql('ALTER TABLE session DROP FOREIGN KEY FK_D044D5D4450D2529');
        $this->addSql('ALTER TABLE texte_genere DROP FOREIGN KEY FK_894AE62D613FECDF');
        $this->addSql('DROP TABLE ajustement_niveau');
        $this->addSql('DROP TABLE enfant');
        $this->addSql('DROP TABLE feedback');
        $this->addSql('DROP TABLE question');
        $this->addSql('DROP TABLE reponse');
        $this->addSql('DROP TABLE session');
        $this->addSql('DROP TABLE texte_genere');
        $this->addSql('DROP TABLE user');
    }
}
