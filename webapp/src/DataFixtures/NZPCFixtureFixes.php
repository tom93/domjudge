<?php declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\ContestSite;
use App\Entity\Team;
use App\Entity\TeamAffiliation;
use App\Entity\TeamCategory;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Psr\Log\LoggerInterface;

/**
 * Class NZPCFixtureFixes
 *
 * Changes the data created by the other fixtures to make it more suitable for
 * the NZPC.
 *
 * @package App\DataFixtures
 */
class NZPCFixtureFixes extends Fixture
{
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * NZPCFixtureFixes constructor.
     * @param LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @inheritDoc
     */
    public function load(ObjectManager $manager)
    {
        // The example affiliation created by TeamAffiliationFixture.php and
        // example categories created by TeamCategoryFixture.php are not
        // required, because the 'nzpc:configure' command creates the standard
        // NZPC categories and affiliations.
        //
        // Update the example team created by TeamFixture.php to use an NZPC
        // affiliation and category, then delete the example affiliation and
        // categories created by the fixtures.

        // update example team
        $team = $manager->getRepository(Team::class)->findOneBy(['externalid' => 'exteam']);
        if (!$team) {
            $this->logger->warning('Cannot find example team "exteam"');
        } else {
            // change affiliation
            $nzpcAffiliation = $manager->getRepository(TeamAffiliation::class)->findOneBy(['name' => 'University of Waikato']);
            if (!$nzpcAffiliation) {
                $this->logger->warning('Cannot find NZPC affiliation "University of Waikato", affiliation of example team will be unset');
            }
            $team->setAffiliation($nzpcAffiliation);
            // change category
            $nzpcCategory = $manager->getRepository(TeamCategory::class)->findOneBy(['name' => 'Tertiary Open']);
            if (!$nzpcCategory) {
                $this->logger->warning('Cannot find NZPC category "Tertiary Open", setting category of example team to "System" instead');
                $team->setCategoryid(1);
            } else {
                $team->setCategory($nzpcCategory);
            }
            // also set a contest site
            $site = $manager->getRepository(ContestSite::class)->findOneBy(['name' => 'Hamilton']);
            if (!$site) {
                $this->logger->warning('Cannot find contest site "Hamilton"');
            } else {
                $team->setSite($site);
            }
        }

        // remove example affiliation
        $affiliation = $manager->getRepository(TeamAffiliation::class)->findOneBy(['externalid' => 'utrecht']);
        if (!$affiliation) {
            $this->logger->warning('Cannot find example affiliation "utrecht" for deletion');
        } else {
            $this->logger->info('Removing example affiliation "Utrecht University"');
            $manager->remove($affiliation);
        }

        $manager->flush();
    }
}
