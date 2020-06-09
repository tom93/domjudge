<?php declare(strict_types=1);

namespace App\Command;

use App\Config\Loader\YamlConfigLoader;
use App\Entity\Configuration;
use App\Service\DOMJudgeService;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class UpdateDatabaseConfigurationCommand
 * @package App\Command
 */
class UpdateDatabaseConfigurationCommand extends Command
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
     * @var string
     */
    protected $etcDir;

    /**
     * UpdateConfigurationCommand constructor.
     *
     * @param EntityManagerInterface $em
     * @param DOMJudgeService        $dj
     * @param LoggerInterface        $logger
     * @param string|null            $name
     */
    public function __construct(
        EntityManagerInterface $em,
        DOMJudgeService $dj,
        LoggerInterface $logger,
        string $name = null
    ) {
        $this->em     = $em;
        $this->dj     = $dj;
        $this->logger = $logger;
        $this->etcDir = $dj->getDomjudgeEtcDir();
        parent::__construct($name);
    }

    /**
     * @inheritDoc
     */
    protected function configure()
    {
        $this
            ->setName('domjudge:db-config:update')
            ->setDescription('Update the database configuration from etc/db-config.yaml')
            ->setHelp('Helps backport configuration changes from DOMjudge 7.3 to 7.2.')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be done without modifying database');
    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // If no verbosity level was specified using -q/-v, increase the verbosity to very verbose
        if ($output->getVerbosity() === OutputInterface::VERBOSITY_NORMAL) {
            $output->setVerbosity(OutputInterface::VERBOSITY_VERY_VERBOSE);
        }

        // Load db-config.yaml (see ConfigurationService.php on DOMjudge 7.3)
        $yamlDbConfigFile = $this->etcDir . '/db-config.yaml';
        $fileLocator      = new FileLocator($this->etcDir);
        $loader           = new YamlConfigLoader($fileLocator);
        $yamlConfig       = $loader->load($yamlDbConfigFile);

        $count = 0;
        foreach ($yamlConfig as $category) {
            foreach ($category['items'] as $item) {
                $this->logger->debug("Processing configuration option '%s'", ['name' => $item['name']]);
                $count++;
                // Check if option already exists in the database
                $option = $this->em->getRepository(Configuration::class)->findOneBy(['name' => $item['name']]);
                if (!$option) {
                    // Add option to database
                    $option = new Configuration();
                    $option->setName($item['name']);
                    $option->setType($item['type']);
                    $option->setValue($item['default_value']);
                    $option->setPublic($item['public']);
                    $option->setDescription($item['description']);
                    $option->setCategory($category['category']);
                    $this->logger->info("Adding configuration option '%s' with value %s", [$item['name'], OutputFormatter::escape($this->dj->jsonEncode($item['default_value']))]);
                    if (!$input->getOption('dry-run')) {
                        $this->em->persist($option);
                        $this->em->flush();
                    }
                } else {
                    // Option already exists, compare and display any differences
                    if ($option->getType() !== $item['type']) {
                        $this->logger->warning("Configuration option '%s': type in database is %s, but type in db-config.yaml is %s", [$item['name'], $option->getType(), $item['type']]);
                    }
                    $currentValue = $option->getType() === 'bool' ? (bool)$option->getValue() : $option->getValue();
                    if ($currentValue !== $item['default_value']) {
                        if ($option->getName() === 'script_filesize_limit' && $currentValue === 540672) {
                            // default changed in commit 053f592d7f4a51027bf8f85af4dfe9a6510aa5ba
                            $this->logger->info("Configuration option '%s': bumping from old default (%s) to new default (%s)", [$item['name'], OutputFormatter::escape($this->dj->jsonEncode($currentValue)), OutputFormatter::escape($this->dj->jsonEncode($item['default_value']))]);
                            if (!$input->getOption('dry-run')) {
                                $option->setValue($item['default_value']);
                                $this->em->flush();
                            }
                        } else {
                            $this->logger->info("Configuration option '%s': value in database is %s, default value db-config.yaml is %s", [$item['name'], OutputFormatter::escape($this->dj->jsonEncode($currentValue)), OutputFormatter::escape($this->dj->jsonEncode($item['default_value']))]);
                        }
                    }
                    if ($option->getPublic() !== $item['public']) {
                        $this->logger->warning("Configuration option '%s': %s in database but %s in db-config.yaml", [$item['name'], $option->getPublic() ? "public" : "not public", $item['public'] ? "public" : "not public"]);
                    }
                    if ($option->getDescription() !== $item['description']) {
                        $this->logger->info("Configuration option '%s': description has changed", [$item['name']]);
                        $this->logger->info("database:       %s", [OutputFormatter::escape($this->dj->jsonEncode($option->getDescription()))]);
                        $this->logger->info("db-config.yaml: %s", [OutputFormatter::escape($this->dj->jsonEncode($item['description']))]);
                    }
                    if ($option->getCategory() !== $category['category']) {
                        $this->logger->info("Configuration option '%s': category in database is %s, category in db-config.yaml is %s", [$item['name'], $option->getCategory(), $category['category']]);
                    }
                }
            }
        }

	$this->logger->info('Processed %d configuration options', [$count]);

        return 0;
    }
}
