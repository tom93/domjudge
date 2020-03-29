<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200329123201 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'add contest sites table';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE contest_site (siteid INT UNSIGNED AUTO_INCREMENT NOT NULL COMMENT \'Contest site ID\', name VARCHAR(255) NOT NULL COMMENT \'Descriptive name\', sortorder TINYINT(1) UNSIGNED DEFAULT \'0\' NOT NULL COMMENT \'Where to sort this site(DC2Type:tinyint)\', active TINYINT(1) DEFAULT \'1\' NOT NULL COMMENT \'Does this site accept new registrations?\', INDEX sortorder (sortorder), PRIMARY KEY(siteid)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB COMMENT = \'Contest sites (for contests that run across multiple locations)\' ');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP TABLE contest_site');
    }
}
