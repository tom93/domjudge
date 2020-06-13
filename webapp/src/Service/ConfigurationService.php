<?php declare(strict_types=1);

namespace App\Service;

use App\Entity\Configuration;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Intl\Exception\NotImplementedException;

/**
 * Class ConfigurationService
 *
 * This is a shim of a class added in DOMjudge 7.3 (commit 464410198, Move
 * specification of configuration variables to YAML file and outside of
 * database, 2020-01-11). The shim helps backport changes from 7.3 to 7.2.
 *
 * To use this shim from a class, define an autowired dependency using the code
 * snippet in ConfigurationService-dependency-snippet.php.
 *
 * This shim does not read the new configuration file etc/db-config.yaml; it
 * only supports configuration data that exists in the database. Use the
 * command `webapp/bin/console domjudge:db-config:update [--dry-run]` to update
 * the database from etc/db-config.yaml.
 *
 * When new configuration options are added to etc/db-config.yaml, the property
 * $defaults should be updated with their default values. Otherwise get() will
 * throw an exception if the user does not run domjudge:db-config:update.
 *
 * @package App\Service
 */
class ConfigurationService
{
    /**
     * @var EntityManagerInterface
     */
    protected $em;

    /**
     * @var DOMJudgeService
     */
    protected $dj;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Default values of new configuration options that might not exist in the
     * database yet if domjudge:db-config:update hasn't been executed.
     *
     * Should match etc/db-config.yaml.
     *
     * @var array
     */
    protected $defaults = [
        'show_team_members' => true,
        'show_teams_with_no_submissions' => true,
        'show_new_affiliation_option' => true,
        'show_user_emails' => true,
        'show_team_managers' => false,
    ];

    /**
     * ConfigurationService constructor.
     *
     * @param EntityManagerInterface $em
     * @param DOMJudgeService        $dj
     * @param LoggerInterface        $logger
     */
    public function __construct(
        EntityManagerInterface $em,
        DOMJudgeService $dj,
        LoggerInterface $logger
    ) {
        $this->em     = $em;
        $this->dj     = $dj;
        $this->logger = $logger;
    }

    /**
     * Get the value for the given configuration name
     *
     * @param string $name         The config name to get the value of
     * @param bool   $onlyIfPublic Only return the value if the config is
     *                             public
     *
     * @return mixed The configuration value
     * @throws Exception If the config can't be found and not default is
     *                   supplied
     */
    public function get(string $name, bool $onlyIfPublic = false)
    {
        $default = $this->defaults[$name] ?? null;
        return $this->dj->dbconfig_get($name, $default, $onlyIfPublic);
    }

    /**
     * Get all the configuration values, indexed by name
     *
     * @param bool $onlyIfPublic
     *
     * @return array
     * @throws Exception
     */
    public function all(bool $onlyIfPublic = false): array
    {
        throw new NotImplementedException();
    }

    /**
     * Get all configuration specifications
     *
     * @throws Exception
     */
    public function getConfigSpecification(): array
    {
        throw new NotImplementedException();
    }

    /**
     * Get the configuration values from the database
     *
     * @return array
     */
    protected function getDbValues(): array
    {
        throw new NotImplementedException();
    }
}
