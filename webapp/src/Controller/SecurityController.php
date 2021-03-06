<?php declare(strict_types=1);

namespace App\Controller;

use App\Entity\ContestSite;
use App\Entity\Role;
use App\Entity\Team;
use App\Entity\TeamAffiliation;
use App\Entity\TeamCategory;
use App\Entity\User;
use App\Form\Type\UserRegistrationType;
use App\Service\DOMJudgeService;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    // backport: use a shim for the ConfigurationService class (added in DOMjudge 7.3)
    protected $config;
    /** @required */
    public function setConfig(\App\Service\ConfigurationService $config)
    {
        $this->config = $config;
    }

    /**
     * @var DOMJudgeService
     */
    private $dj;

    public function __construct(DOMJudgeService $dj)
    {
        $this->dj = $dj;
    }

    /**
     * @Route("/login", name="login")
     * @param Request                       $request
     * @param AuthorizationCheckerInterface $authorizationChecker
     * @param AuthenticationUtils           $authUtils
     * @return RedirectResponse|Response
     * @throws Exception
     */
    public function loginAction(
        Request $request,
        AuthorizationCheckerInterface $authorizationChecker,
        AuthenticationUtils $authUtils
    )
    {
        $allowIPAuth = false;
        $authmethods = $this->dj->dbconfig_get('auth_methods', []);

        if (in_array('ipaddress', $authmethods)) {
            $allowIPAuth = true;
        }

        $ipAutologin = $this->dj->dbconfig_get('ip_autologin', false);
        if ($authorizationChecker->isGranted('IS_AUTHENTICATED_FULLY') && !$ipAutologin) {
            return $this->redirect($this->generateUrl('root'));
        }

        // get the login error if there is one
        $error = $authUtils->getLastAuthenticationError();

        // last username entered by the user
        $lastUsername = $authUtils->getLastUsername();

        $em = $this->getDoctrine()->getManager();

        $clientIP             = $this->dj->getClientIp();
        $auth_ipaddress_users = [];
        if ($allowIPAuth) {
            $auth_ipaddress_users = $em->getRepository(User::class)->findBy(['ipAddress' => $clientIP]);
        }

        // Add a header so we can detect that this is the login page
        $response = new Response();
        $response->headers->set('X-Login-Page', $this->generateUrl('login'));

        $selfRegistrationCategoriesCount = $em->getRepository(TeamCategory::class)->count(['allow_self_registration' => 1]);

        return $this->render('security/login.html.twig', array(
            'last_username' => $lastUsername,
            'error' => $error,
            'allow_registration' => $selfRegistrationCategoriesCount !== 0,
            'allowed_authmethods' => $authmethods,
            'auth_xheaders_present' => $request->headers->get('X-DOMjudge-Login'),
            'auth_ipaddress_users' => $auth_ipaddress_users,
        ), $response);
    }

    /**
     * @Route("/register", name="register")
     * @param Request                       $request
     * @param AuthorizationCheckerInterface $authorizationChecker
     * @param UserPasswordEncoderInterface  $passwordEncoder
     * @return RedirectResponse|Response
     * @throws Exception
     */
    public function registerAction(
        Request $request,
        AuthorizationCheckerInterface $authorizationChecker,
        UserPasswordEncoderInterface $passwordEncoder
    )
    {
        // Redirect if already logged in
        if ($authorizationChecker->isGranted('IS_AUTHENTICATED_FULLY')) {
            return $this->redirect($this->generateUrl('root'));
        }

        $em                              = $this->getDoctrine()->getManager();
        $selfRegistrationCategoriesCount = $em->getRepository(TeamCategory::class)->count(['allow_self_registration' => 1]);

        if ($selfRegistrationCategoriesCount === 0) {
            throw new HttpException(400, "Registration not enabled");
        }

        $user              = new User();
        $registration_form = $this->createForm(UserRegistrationType::class, $user);
        $registration_form->handleRequest($request);
        if ($registration_form->isSubmitted() && $registration_form->isValid()) {
            $team_role = $em->getRepository(Role::class)->findOneBy(['dj_role' => 'team']);

            $plainPass = $registration_form->get('plainPassword')->getData();
            $password  = $passwordEncoder->encodePassword($user, $plainPass);
            $user->setPassword($password);
            $user->setName($user->getUsername());
            $user->addUserRole($team_role);

            $teamName = $registration_form->get('teamName')->getData();

            if ($selfRegistrationCategoriesCount === 1) {
                $teamCategory = $em->getRepository(TeamCategory::class)->findOneBy(['allow_self_registration' => 1]);
            } else {
                // $selfRegistrationCategoriesCount > 1, 'teamCategory' field exists
                $teamCategory = $registration_form->get('teamCategory')->getData();
            }

            // Create a team to go with the user, then set some team attributes
            $team = new Team();
            $user->setTeam($team);
            $team
                ->addUser($user)
                ->setName($teamName)
                ->setCategory($teamCategory)
                ->setComments('Registered by ' . $this->dj->getClientIp() . ' on ' . date('r'));

            $contestSitesCount = $em->getRepository(ContestSite::class)->count(['active' => 1]);
            if ($contestSitesCount === 1) {
                $team->setSite($em->getRepository(ContestSite::class)->findOneBy(['active' => 1]));
            } elseif ($contestSitesCount > 1) {
                $team->setSite($registration_form->get('contestSite')->getData());
            }

            if ($this->config->get('show_team_managers')) {
                $team->setTeamManagerName($registration_form->get('teamManagerName')->getData());
            }
            if ($this->config->get('show_team_manager_emails')) {
                $team->setTeamManagerEmail($registration_form->get('teamManagerEmail')->getData());
            }

            if ($this->config->get('show_team_members')) {
                $team->setMembers($registration_form->get('members')->getData());
            }

            if ($this->config->get('show_affiliations')) {
                $affiliationData = $registration_form->get('affiliation')->getData();
                if ($affiliationData === 'none') {
                    $affiliation = null;
                } elseif ($affiliationData === 'new') {
                    $affiliation = new TeamAffiliation();
                    $affiliation
                        ->setName($registration_form->get('affiliationName')->getData())
                        ->setShortname($registration_form->get('affiliationName')->getData());
                    if ($registration_form->has('affiliationCountry')) {
                        $affiliation->setCountry($registration_form->get('affiliationCountry')->getData());
                    }
                    $em->persist($affiliation);
                } else {
                    $affiliation = $affiliationData;
                }
                $team->setAffiliation($affiliation);
            }

            $em->persist($user);
            $em->persist($team);
            $em->flush();

            $this->addFlash('success', 'Account registered successfully. Please log in.');

            return $this->redirect($this->generateUrl('login'));
        }

        return $this->render('security/register.html.twig', array(
            'registration_form' => $registration_form->createView(),
        ));
    }
}
