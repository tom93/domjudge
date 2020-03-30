<?php declare(strict_types=1);

namespace App\Controller\Jury;

use App\Controller\BaseController;
use App\Entity\ContestSite;
use App\Entity\Submission;
use App\Form\Type\ContestSiteType;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use App\Service\SubmissionService;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/jury/sites")
 * @IsGranted("ROLE_JURY")
 */
class ContestSiteController extends BaseController
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
     * @var ConfigurationService
     */
    protected $config;

    /**
     * @var KernelInterface
     */
    protected $kernel;

    /**
     * @var EventLogService
     */
    protected $eventLogService;

    /**
     * ContestSiteController constructor.
     *
     * @param EntityManagerInterface $em
     * @param DOMJudgeService        $dj
     * @param ConfigurationService   $config
     * @param KernelInterface        $kernel
     * @param EventLogService        $eventLogService
     */
    public function __construct(
        EntityManagerInterface $em,
        DOMJudgeService $dj,
        ConfigurationService $config,
        KernelInterface $kernel,
        EventLogService $eventLogService
    ) {
        $this->em              = $em;
        $this->dj              = $dj;
        $this->config          = $config;
        $this->eventLogService = $eventLogService;
        $this->kernel          = $kernel;
    }

    /**
     * @Route("", name="jury_contest_sites")
     */
    public function indexAction(Request $request, Packages $assetPackage)
    {
        $em           = $this->em;
        $contestSites = $em->createQueryBuilder()
            ->select('s', 'COUNT(t.teamid) AS num_teams')
            ->from(ContestSite::class, 's')
            ->leftJoin('s.teams', 't')
            ->orderBy('s.sortorder', 'ASC')
            ->addOrderBy('s.name', 'ASC')
            ->addOrderBy('s.siteid', 'ASC')
            ->groupBy('s.siteid')
            ->getQuery()->getResult();
        $table_fields = [
            'siteid' => ['title' => 'ID', 'sort' => true],
            'sortorder' => ['title' => 'sort', 'sort' => true, 'default_sort' => true],
            'name' => ['title' => 'name', 'sort' => true],
            'num_teams' => ['title' => '# teams', 'sort' => true],
            'active' => ['title' => 'active', 'sort' => true],
        ];

        $propertyAccessor    = PropertyAccess::createPropertyAccessor();
        $contest_sites_table = [];
        foreach ($contestSites as $contestSiteData) {
            /** @var ContestSite $contestSite */
            $contestSite = $contestSiteData[0];
            $sitedata    = [];
            $siteactions = [];
            // Get whatever fields we can from the site object itself
            foreach ($table_fields as $k => $v) {
                if ($propertyAccessor->isReadable($contestSite, $k)) {
                    $sitedata[$k] = ['value' => $propertyAccessor->getValue($contestSite, $k)];
                }
            }

            if ($this->isGranted('ROLE_ADMIN')) {
                $siteactions[] = [
                    'icon' => 'edit',
                    'title' => 'edit this site',
                    'link' => $this->generateUrl('jury_contest_site_edit', [
                        'siteId' => $contestSite->getSiteid(),
                    ])
                ];
                $siteactions[] = [
                    'icon' => 'trash-alt',
                    'title' => 'delete this site',
                    'link' => $this->generateUrl('jury_contest_site_delete', [
                        'siteId' => $contestSite->getSiteid(),
                    ]),
                    'ajaxModal' => true,
                ];
            }

            $sitedata['num_teams'] = ['value' => $contestSiteData['num_teams']];
            $sitedata['active']    = ['value' => $contestSite->getActive() ? 'yes' : 'no'];

            $contest_sites_table[] = [
                'data' => $sitedata,
                'actions' => $siteactions,
                'link' => $this->generateUrl('jury_contest_site', ['siteId' => $contestSite->getSiteid()]),
            ];
        }
        return $this->render('jury/contest_sites.html.twig', [
            'contest_sites' => $contest_sites_table,
            'table_fields' => $table_fields,
            'num_actions' => $this->isGranted('ROLE_ADMIN') ? 2 : 0,
        ]);
    }

    /**
     * @Route("/{siteId<\d+>}", name="jury_contest_site")
     * @param Request           $request
     * @param SubmissionService $submissionService
     * @param int               $siteId
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function viewAction(Request $request, SubmissionService $submissionService, int $siteId)
    {
        /** @var ContestSite $contestSite */
        $contestSite = $this->em->getRepository(ContestSite::class)->find($siteId);
        if (!$contestSite) {
            throw new NotFoundHttpException(sprintf('Contest site with ID %s not found', $siteId));
        }

        $restrictions = ['siteid' => $contestSite->getSiteid()];
        /** @var Submission[] $submissions */
        list($submissions, $submissionCounts) = $submissionService->getSubmissionList(
            $this->dj->getCurrentContests(),
            $restrictions
        );

        $data = [
            'contestSite' => $contestSite,
            'submissions' => $submissions,
            'submissionCounts' => $submissionCounts,
            'showContest' => count($this->dj->getCurrentContests()) > 1,
            'refresh' => [
                'after' => 15,
                'url' => $this->generateUrl('jury_contest_site', ['siteId' => $contestSite->getSiteid()]),
                'ajax' => true,
            ],
        ];

        // For ajax requests, only return the submission list partial
        if ($request->isXmlHttpRequest()) {
            $data['showTestcases'] = false;
            return $this->render('jury/partials/submission_list.html.twig', $data);
        }

        return $this->render('jury/contest_site.html.twig', $data);
    }

    /**
     * @Route("/{siteId<\d+>}/edit", name="jury_contest_site_edit")
     * @IsGranted("ROLE_ADMIN")
     * @param Request $request
     * @param int     $siteId
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     * @throws \Exception
     */
    public function editAction(Request $request, int $siteId)
    {
        /** @var ContestSite $contestSite */
        $contestSite = $this->em->getRepository(ContestSite::class)->find($siteId);
        if (!$contestSite) {
            throw new NotFoundHttpException(sprintf('Contest site with ID %s not found', $siteId));
        }

        $form = $this->createForm(ContestSiteType::class, $contestSite);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->saveEntity($this->em, $this->eventLogService, $this->dj, $contestSite,
                              $contestSite->getSiteid(), false);
            return $this->redirectToRoute('jury_contest_site', ['siteId' => $contestSite->getSiteid()]);
        }

        return $this->render('jury/contest_site_edit.html.twig', [
            'contestSite' => $contestSite,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{siteId<\d+>}/delete", name="jury_contest_site_delete")
     * @IsGranted("ROLE_ADMIN")
     * @param Request $request
     * @param int     $siteId
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     * @throws \Exception
     */
    public function deleteAction(Request $request, int $siteId)
    {
        /** @var ContestSite $contestSite */
        $contestSite = $this->em->getRepository(ContestSite::class)->find($siteId);
        if (!$contestSite) {
            throw new NotFoundHttpException(sprintf('Contest site with ID %s not found', $siteId));
        }

        return $this->deleteEntity($request, $this->em, $this->dj, $this->kernel, $contestSite,
                                   $contestSite->getName(), $this->generateUrl('jury_contest_sites'));
    }

    /**
     * @Route("/add", name="jury_contest_site_add")
     * @IsGranted("ROLE_ADMIN")
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Exception
     */
    public function addAction(Request $request)
    {
        $contestSite = new ContestSite();

        $form = $this->createForm(ContestSiteType::class, $contestSite);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($contestSite);
            $this->saveEntity($this->em, $this->eventLogService, $this->dj, $contestSite,
                              $contestSite->getSiteid(), true);
            return $this->redirectToRoute('jury_contest_site', ['siteId' => $contestSite->getSiteid()]);
        }

        return $this->render('jury/contest_site_add.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
