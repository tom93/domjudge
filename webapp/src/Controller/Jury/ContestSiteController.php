<?php declare(strict_types=1);
// based on TeamCategoryController.php

namespace App\Controller\Jury;

use App\Controller\BaseController;
use App\DataTransferObject\SubmissionRestriction;
use App\Entity\ContestSite;
use App\Entity\Submission;
use App\Form\Type\ContestSiteType;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use App\Service\SubmissionService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_JURY')]
#[Route(path: '/jury/sites')]
class ContestSiteController extends BaseController
{
    public function __construct(
        protected readonly EntityManagerInterface $em,
        protected readonly DOMJudgeService $dj,
        protected readonly ConfigurationService $config,
        protected readonly KernelInterface $kernel,
        protected readonly EventLogService $eventLogService
    ) {}

    #[Route(path: '', name: 'jury_contest_sites')]
    public function indexAction(): Response
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
            // Get whatever fields we can from the site object itself.
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
        ]);
    }

    /**
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    #[Route(path: '/{siteId<\d+>}', name: 'jury_contest_site')]
    public function viewAction(Request $request, SubmissionService $submissionService, int $siteId): Response
    {
        $contestSite = $this->em->getRepository(ContestSite::class)->find($siteId);
        if (!$contestSite) {
            throw new NotFoundHttpException(sprintf('Contest site with ID %s not found', $siteId));
        }

        /** @var Submission[] $submissions */
        [$submissions, $submissionCounts] = $submissionService->getSubmissionList(
            $this->dj->getCurrentContests(honorCookie: true),
            new SubmissionRestriction(siteId: $contestSite->getSiteid())
        );

        $data = [
            'contestSite' => $contestSite,
            'submissions' => $submissions,
            'submissionCounts' => $submissionCounts,
            'showContest' => count($this->dj->getCurrentContests(honorCookie: true)) > 1,
            'refresh' => [
                'after' => 15,
                'url' => $this->generateUrl('jury_contest_site', ['siteId' => $contestSite->getSiteid()]),
                'ajax' => true,
            ],
        ];

        // For ajax requests, only return the submission list partial.
        if ($request->isXmlHttpRequest()) {
            $data['showTestcases'] = false;
            return $this->render('jury/partials/submission_list.html.twig', $data);
        }

        return $this->render('jury/contest_site.html.twig', $data);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route(path: '/{siteId<\d+>}/edit', name: 'jury_team_site_edit')]
    public function editAction(Request $request, int $siteId): Response
    {
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
            'form' => $form,
        ]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route(path: '/{siteId<\d+>}/delete', name: 'jury_team_site_delete')]
    public function deleteAction(Request $request, int $siteId): Response
    {
        $contestSite = $this->em->getRepository(ContestSite::class)->find($siteId);
        if (!$contestSite) {
            throw new NotFoundHttpException(sprintf('Contest site with ID %s not found', $siteId));
        }

        return $this->deleteEntities($request, $this->em, $this->dj, $this->eventLogService, $this->kernel,
                                     [$contestSite], $this->generateUrl('jury_contest_sites'));
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route(path: '/add', name: 'jury_team_site_add')]
    public function addAction(Request $request): Response
    {
        $contestSite = new ContestSite();

        $form = $this->createForm(ContestSiteType::class, $contestSite);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($contestSite);
            $this->saveEntity($this->em, $this->eventLogService, $this->dj, $contestSite, null, true);
            return $this->redirectToRoute('jury_contest_site', ['siteId' => $contestSite->getSiteid()]);
        }

        return $this->render('jury/contest_site_add.html.twig', [
            'form' => $form,
        ]);
    }
}
