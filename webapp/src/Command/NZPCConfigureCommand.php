<?php declare(strict_types=1);

namespace App\Command;

use App\Entity\Configuration;
use App\Entity\ContestSite;
use App\Entity\TeamAffiliation;
use App\Entity\TeamCategory;
use App\Entity\Language;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\ImportExportService;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class NZPCConfigureCommand
 * @package App\Command
 */
class NZPCConfigureCommand extends Command
{
    const CATEGORY_NAMES = ['High School', 'Tertiary Junior', 'Tertiary Intermediate', 'Tertiary Open', 'Open'];
    const CATEGORY_COLORS = ['#fffca6', '#ffcf9e', '#91b2ff', '#fff957', '#ff9cfc'];

    const CONTEST_SITE_NAMES = ['Auckland', 'Hamilton', 'Invercargill', 'Christchurch', 'Wellington', 'Dunedin'];

    const LANGUAGE_IDS = ['c', 'cpp', 'java', 'py3', 'csharp'];

    /**
     * @var EntityManagerInterface
     */
    protected $em;

    /**
     * @var DOMJudgeService
     */
    protected $dj;

    /**
     * @var ConfigurationService
     */
    protected $config;

    /**
     * @var ImportExportService
     */
    protected $importExportService;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var string
     */
    protected $sqlDir;

    /**
     * NZPCConfigureCommand constructor.
     *
     * @param EntityManagerInterface $em
     * @param DOMJudgeService        $dj
     * @param ConfigurationService   $config
     * @param ImportExportService    $importExportService
     * @param LoggerInterface        $logger
     * @param string                 $sqlDir
     * @param string|null            $name
     */
    public function __construct(
        EntityManagerInterface $em,
        DOMJudgeService $dj,
        ConfigurationService $config,
        ImportExportService $importExportService,
        LoggerInterface $logger,
        string $sqlDir,
        string $name = null
    ) {
        $this->em                  = $em;
        $this->dj                  = $dj;
        $this->config              = $config;
        $this->importExportService = $importExportService;
        $this->logger              = $logger;
        $this->sqlDir              = $sqlDir;
        parent::__construct($name);
    }

    /**
     * @inheritDoc
     */
    protected function configure()
    {
        $this
            ->setName('nzpc:configure')
            ->setDescription('Load the NZPC configuration')
            ->setHelp(<<<EOF
Run all the configuration tasks:

    nzpc:configure --all

Run specific configuration tasks:

    nzpc:configure --TASK [--TASK...]

Run all the configuration tasks except for some tasks:

    nzpc:configure --all --no-TASK [--no-TASK...]
EOF
            )
            ->addOption('all', null, InputOption::VALUE_NONE, 'Run all the configuration tasks')
            ->addOption('config', null, InputOption::VALUE_NONE, 'Set the configuration options')
            ->addOption('no-config', null, InputOption::VALUE_NONE)
            ->addOption('categories', null, InputOption::VALUE_NONE, 'Load the standard NZPC categories')
            ->addOption('no-categories', null, InputOption::VALUE_NONE)
            ->addOption('sites', null, InputOption::VALUE_NONE, 'Load the usual NZPC contest sites')
            ->addOption('no-sites', null, InputOption::VALUE_NONE)
            ->addOption('affiliations', null, InputOption::VALUE_NONE, 'Load the default NZPC affiliations')
            ->addOption('no-affiliations', null, InputOption::VALUE_NONE)
            ->addOption('languages', null, InputOption::VALUE_NONE, 'Allow submissions in the default languages')
            ->addOption('no-languages', null, InputOption::VALUE_NONE)
            ->addOption('enable-registration', null, InputOption::VALUE_NONE, 'Enable registration (not included in --all, must be specified explicitly)')
            ->addOption('disable-registration', null, InputOption::VALUE_NONE, 'Disable registration')
            ->addOption('export-affiliations', null, InputOption::VALUE_NONE, 'Output the existing affiliations in TSV format')
            ->addOption('affiliations-data', null, InputOption::VALUE_REQUIRED, 'Path of affiliation data to load, in TSV format with columns shortname and name',
                        sprintf('%s/files/organizations-nzpc.tsv', $this->sqlDir));
    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($input->getOption('enable-registration') && $input->getOption('disable-registration')) {
            throw new InvalidArgumentException('The "--enable-registration" and "--disable-registration" options are mutually exclusive.');
        }

        // If no verbosity level was specified using -q/-v, increase the verbosity to very verbose
        if ($output->getVerbosity() === OutputInterface::VERBOSITY_NORMAL) {
            $output->setVerbosity(OutputInterface::VERBOSITY_VERY_VERBOSE);
        }

        $tasksCount = 0;
        if ($this->isTaskEnabled($input, 'config')) {
            $tasksCount++;
            $this->taskConfigure();
        }
        if ($this->isTaskEnabled($input, 'categories')) {
            $tasksCount++;
            $this->taskCategories();
        }
        if ($this->isTaskEnabled($input, 'sites')) {
            $tasksCount++;
            $this->taskSites();
        }
        if ($this->isTaskEnabled($input, 'affiliations')) {
            $tasksCount++;
            $this->taskAffiliations($input->getOption('affiliations-data'));
        }
        if ($this->isTaskEnabled($input, 'languages')) {
            $tasksCount++;
            $this->taskLanguages();
        }

        if ($input->getOption('enable-registration')) {
            $tasksCount++;
            $this->enableRegistration();
        }
        if ($input->getOption('disable-registration')) {
            $tasksCount++;
            $this->disableRegistration();
        }
        if ($input->getOption('export-affiliations')) {
            $tasksCount++;
            $this->taskExportAffiliations($output);
        }

        if ($tasksCount === 0) {
            $this->logger->warning('No tasks specified');
            if (!$input->getOption('all')) {
                $this->logger->info('Hint: Use --all to run all the tasks, or --help to see available options');
            }
        }

        return 0;
    }

    /**
     * Set the NZPC configuration options
     */
    protected function taskConfigure()
    {
        $this->logger->notice('Setting configuration options for NZPC');
        $this->doConfigure('show_flags', false); // hides "Affiliation country" field during registration
        $this->doConfigure('show_new_affiliation_option', false);
        $this->doConfigure('show_user_emails', false);
        $this->doConfigure('show_team_managers', true);
        $this->doConfigure('show_team_manager_emails', true);
        // $this->doConfigure('show_teams_with_no_submissions', false); // not used in NZPC
    }

    /**
     * Set a configuration option if it hasn't been set yet
     *
     * @param string $name
     * @param mixed  $value
     */
    protected function doConfigure(string $name, $value)
    {
        if (true) {
            // backport: on DOMjudge < 7.3, there is no db-config.yaml and all the configuration data is stored in the database
            /** @var Configuration $option */
            $option = $this->em->getRepository(Configuration::class)->findOneBy(['name' => $name]);
            if (!$option) {
                // don't try to create options that don't exist because it's too complicated (use `webapp/bin/console domjudge:db-config:update`)
                $this->logger->warning("Configuration option '%s' does not exist", [$name]);
            } else {
                $current = $this->config->get($name);
                if ($current === $value || (is_bool($value) && $current === (int)$value)) {
                    $this->logger->debug("Configuration option '%s' is already set to %s", [$name, OutputFormatter::escape($this->dj->jsonEncode($value))]);
                } else {
                    // it's not possible to detect if the option has been set by the user, so always overwrite
                    $this->logger->info("Setting configuration option '%s' to %s (was %s)", [$name, OutputFormatter::escape($this->dj->jsonEncode($value)), OutputFormatter::escape($this->dj->jsonEncode($current))]);
                    $option->setValue($value);
                    $this->em->flush();
                }
            }
            return;
        }
        $current = $this->config->get($name);
        if ($current === $value || (is_bool($value) && $current === (int)$value)) {
            $this->logger->debug("Configuration option '%s' is already set to %s", [$name, OutputFormatter::escape($this->dj->jsonEncode($value))]);
        } elseif ($this->isConfigurationSet($name)) {
            $this->logger->warning("Skipping configuration option '%s' because it has already been set to a different value: %s", [$name, OutputFormatter::escape($this->dj->jsonEncode($current))]);
        } else {
            $this->logger->info("Setting configuration option '%s' to %s", [$name, OutputFormatter::escape($this->dj->jsonEncode($value))]);
            $this->setConfiguration($name, $value);
        }
    }

    /**
     * Load the standard NZPC categories
     */
    protected function taskCategories()
    {
        $this->logger->notice('Loading team categories');
        foreach (self::CATEGORY_NAMES as $index => $name) {
            if ($this->em->getRepository(TeamCategory::class)->count(['name' => $name]) === 0) {
                $this->logger->info("Creating new category '%s'", [$name]);
                $category = new TeamCategory();
                $category->setName($name);
                $category->setSortorder($index);
                $category->setColor(self::CATEGORY_COLORS[$index]);
                $this->em->persist($category);
                $this->em->flush();
            } else {
                $this->logger->debug("Category '%s' already exists", [$name]);
            }
        }
    }

    /**
     * Load the usual NZPC contest sites
     */
    protected function taskSites()
    {
        $this->logger->notice('Loading contest sites');
        foreach (self::CONTEST_SITE_NAMES as $index => $name) {
            if ($this->em->getRepository(ContestSite::class)->count(['name' => $name]) === 0) {
                $this->logger->info("Creating new contest site '%s'", [$name]);
                $site = new ContestSite();
                $site->setName($name);
                $site->setSortorder($index);
                $this->em->persist($site);
                $this->em->flush();
            } else {
                $this->logger->debug("Contest site '%s' already exists", [$name]);
            }
        }
    }

    /**
     * Load affiliations
     *
     * @param string $path
     * @throws Exception
     */
    protected function taskAffiliations(string $path)
    {
        $this->logger->notice('Loading affiliations');
        $content = file($path);
        $count   = 0;
        $l       = 0;
        foreach ($content as $line) {
            $l++;
            $row = explode("\t", rtrim($line, "\r\n"));
            if (count($row) !== 2) {
                throw new Exception(sprintf('Error importing affiliations from %s: line %d: expected 2 tab-separated values, found %d',
                                            OutputFormatter::escape($path), $l, count($row)));
            } else {
                $shortname = $row[0];
                $name      = $row[1];
                if ($this->em->getRepository(TeamAffiliation::class)->count(['shortname' => $shortname]) === 0) {
                    $count++;
                    $this->logger->debug("Importing affiliation '%s'", [OutputFormatter::escape($name)]);
                    $affiliation = new TeamAffiliation();
                    $affiliation->setShortname($shortname);
                    $affiliation->setName($name);
                    $this->em->persist($affiliation);
                    $this->em->flush();
                } else {
                    $this->logger->debug("Team affiliation '%s' already exists", [OutputFormatter::escape($shortname)]);
                }
            }
        }
        $this->logger->info('%d affiliations imported', [$count]);
    }

    /**
     * Export affiliations
     *
     * @param OutputInterface $output
     */
    protected function taskExportAffiliations(OutputInterface $output)
    {
        $affiliations = $this->em->getRepository(TeamAffiliation::class)->findBy([], ['name' => 'ASC']);
        foreach ($affiliations as $affiliation) {
            $row  = [$affiliation->getShortname(), $affiliation->getName()];
            $line = implode("\t", str_replace(["\t", "\n", "\r"], " ", $row)) . "\n";
            $output->write($line, false /*$newline*/, OutputInterface::OUTPUT_RAW);
        }
    }

    /**
     * Allow submissions in the default languages
     */
    protected function taskLanguages()
    {
        $this->logger->notice('Enabling languages');
        foreach (self::LANGUAGE_IDS as $langid) {
            $lang = $this->em->getRepository(Language::class)->find($langid);
            if (!$lang) {
                $this->logger->warning("Language with ID '%s' not found", [$langid]);
            } elseif ($lang->getAllowSubmit()) {
                $this->logger->debug("Language '%s' already allows submissions", [$langid]);
            } else {
                $this->logger->info("Allowing submissions in language '%s' (%s)", [$langid, $lang->getName()]);
                $lang->setAllowSubmit(true);
                $this->em->flush();
            }
        }
        $nonDefaultLanguages = $this->em->createQueryBuilder()
            ->from(Language::class, 'l')
            ->select('l')
            ->andWhere('l.allowSubmit = 1')
            ->andWhere('l.langid NOT IN (:defaultLanguageIds)')
            ->setParameter(':defaultLanguageIds', self::LANGUAGE_IDS)
            ->getQuery()
            ->getResult();
        foreach ($nonDefaultLanguages as $lang) {
            $this->logger->warning("Non-default language '%s' (%s) is set to allow submissions", [$lang->getLangid(), $lang->getName()]);
        }
    }

    /**
     * Enable registration
     */
    protected function enableRegistration()
    {
        $this->logger->notice('Enabling registration');
        foreach (self::CATEGORY_NAMES as $name) {
            $category = $this->em->getRepository(TeamCategory::class)->findOneBy(['name' => $name]);
            if (!$category) {
                $this->logger->warning("Category '%s' not found", [$name]);
            } elseif ($category->getAllowSelfRegistration()) {
                $this->logger->debug("Registration is already enabled for category '%s'", [$name]);
            } else {
                $this->logger->info("Enabling registration for category '%s'", [$name]);
                $category->setAllowSelfRegistration(true);
                $this->em->flush();
            }
        }
    }

    /**
     * Disable registration
     */
    protected function disableRegistration()
    {
        $this->logger->notice('Disabling registration');
        $categories = $this->em->getRepository(TeamCategory::class)->findBy(['allow_self_registration' => 1]);
        if (count($categories) === 0) {
            $this->logger->info('Registration is already disabled');
        } else {
            foreach ($categories as $category) {
                $this->logger->info("Disabling registration for category '%s'", [$category->getName()]);
                $category->setAllowSelfRegistration(false);
                $this->em->flush();
            }
        }
    }

    /**
     * Check if a task should be executed
     *
     * @param InputInterface  $input
     * @param string          $task
     * @return bool
     */
    protected function isTaskEnabled(InputInterface $input, string $task)
    {
        if ($input->getOption('no-' . $task)) {
            return false;
        } elseif ($input->getOption($task)) {
            return true;
        } elseif ($input->getOption('all')) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Check if a configuration option has been set
     *
     * @param string $name
     * @return bool
     */
    protected function isConfigurationSet(string $name)
    {
        return $this->em->getRepository(Configuration::class)->count(['name' => $name]) > 0;
    }

    /**
     * Set a configuration option
     *
     * @param string $name
     * @param mixed  $value
     */
    protected function setConfiguration(string $name, $value)
    {
        /** @var Configuration $option */
        $option = $this->em->getRepository(Configuration::class)->findOneBy(['name' => $name]);
        if (!$option) {
            $option = new Configuration();
            $option->setName($name);
            $this->em->persist($option);
        }
        $option->setValue($value);
        $this->em->flush();
    }
}
