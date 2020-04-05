<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200405142429 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'add contest site to teams';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE team ADD siteid INT UNSIGNED DEFAULT NULL COMMENT \'Contest site ID\'');
        $this->addSql('ALTER TABLE team ADD CONSTRAINT team_ibfk_3 FOREIGN KEY (siteid) REFERENCES contest_site (siteid) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX siteid ON team (siteid)');
     }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE team DROP FOREIGN KEY team_ibfk_3');
        $this->addSql('DROP INDEX siteid ON team');
        $this->addSql('ALTER TABLE team DROP siteid');
    }
}
