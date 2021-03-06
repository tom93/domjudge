<?php declare(strict_types=1);

namespace App\Form\Type;

use App\Entity\ContestSite;
use App\Entity\Team;
use App\Entity\TeamAffiliation;
use App\Entity\TeamCategory;
use App\Entity\User;
use App\Service\DOMJudgeService;
use App\Utils\Utils;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Context\ExecutionContext;

class UserRegistrationType extends AbstractType
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
    protected $dj;

    /**
     * @var EntityManagerInterface
     */
    protected $em;

    /**
     * UserRegistrationType constructor.
     * @param DOMJudgeService        $dj
     * @param EntityManagerInterface $em
     */
    public function __construct(DOMJudgeService $dj, EntityManagerInterface $em)
    {
        $this->dj = $dj;
        $this->em = $em;
    }

    /**
     * @inheritDoc
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('username', TextType::class, [
                'label' => false,
                'attr' => [
                    'placeholder' => 'Username',
                    'title' => 'Used to log in to this contest website. Must be alphanumeric.',
                ],
            ]);
        if ($this->config->get('show_user_emails')) {
            $builder
                ->add('email', EmailType::class, [
                    'label' => false,
                    'required' => false,
                    'attr' => [
                        'placeholder' => 'Email address (optional)',
                    ],
                    'constraints' => new Email(),
                ]);
        }
        $builder
            ->add('teamName', TextType::class, [
                'label' => false,
                'attr' => [
                    'placeholder' => 'Team name',
                    'title' => 'Displayed on the scoreboard.',
                ],
                'constraints' => [
                    new NotBlank(),
                    new Callback(function ($teamName, ExecutionContext $context) {
                        if ($this->em->getRepository(Team::class)->findOneBy(['name' => $teamName])) {
                            $context->buildViolation('This team name is already in use.')
                                ->addViolation();
                        }
                    }),
                ],
                'mapped' => false,
            ]);

        if ($this->config->get('show_team_managers')) {
            $builder
                ->add('teamManagerName', TextType::class, [
                    'label' => false,
                    'attr' => [
                        'placeholder' => 'Team manager name',
                    ],
                    'constraints' => [
                        new NotBlank(),
                    ],
                    'mapped' => false,
                ]);
        }
        if ($this->config->get('show_team_manager_emails')) {
            $builder
                ->add('teamManagerEmail', EmailType::class, [
                    'label' => false,
                    'attr' => [
                        'placeholder' => 'Team manager email address',
                    ],
                    'constraints' => new Email(),
                    'mapped' => false,
                ]);
        }

        $selfRegistrationCategoriesCount = $this->em->getRepository(TeamCategory::class)->count(['allow_self_registration' => 1]);
        if ($selfRegistrationCategoriesCount > 1) {
            $builder
                ->add('teamCategory', EntityType::class, [
                    'class' => TeamCategory::class,
                    'label' => false,
                    'mapped' => false,
                    'choice_label' => 'name',
                    'placeholder' => '-- Select category --',
                    'query_builder' => function (EntityRepository $er) {
                        return $er
                            ->createQueryBuilder('c')
                            ->where('c.allow_self_registration = 1')
                            ->orderBy('c.sortorder');
                    },
                    'attr' => [
                        'placeholder' => 'Category',
                    ],
                    'constraints' => [
                        new NotBlank(),
                    ],
                ]);
        }

        $contestSitesCount = $this->em->getRepository(ContestSite::class)->count(['active' => 1]);
        if ($contestSitesCount > 1) {
            $builder
                ->add('contestSite', EntityType::class, [
                    'class' => ContestSite::class,
                    'label' => false,
                    'mapped' => false,
                    'choice_label' => 'name',
                    'placeholder' => '-- Select contest site --',
                    'query_builder' => function (EntityRepository $er) {
                        return $er
                            ->createQueryBuilder('s')
                            ->where('s.active = 1')
                            ->orderBy('s.sortorder')
                            ->addOrderBy('s.name', 'ASC')
                            ->addOrderBy('s.siteid');
                    },
                    'constraints' => [
                        new NotBlank(),
                    ],
                ]);
        }

        if ($this->config->get('show_team_members')) {
            $builder
                ->add('members', TextareaType::class, [
                    'label' => false,
                    'mapped' => false,
                    'attr' => [
                        'placeholder' => 'Team members (optional)',
                    ],
                    'required' => false,
                    'mapped' => false,
                ]);
        }

        if ($this->config->get('show_affiliations')) {
            $specialAffiliationChoices = [];
            $specialAffiliationChoices['No affiliation'] = 'none';
            if ($this->config->get('show_new_affiliation_option')) {
                $specialAffiliationChoices['Add affiliation...'] = 'new';
            }
            $affiliations = [];
            foreach ($this->em->getRepository(TeamAffiliation::class)->findBy([], ['name' => 'ASC']) as $affiliation) {
                $affiliations[$affiliation->getName()] = $affiliation;
            }

            $countries = [];
            foreach (Utils::ALPHA3_COUNTRIES as $alpha3 => $country) {
                $countries["$country ($alpha3)"] = $alpha3;
            }

            $builder
                ->add('affiliation', ChoiceType::class, [
                    // Note: it is important that $specialAffiliationChoices takes precedence over user-created affiliations with the same names
                    'choices' => $specialAffiliationChoices + $affiliations,
                    'preferred_choices' => array_values($specialAffiliationChoices),
                    'mapped' => false,
                    'label' => false,
                    'placeholder' => '-- Select affiliation --',
                    'choice_value' => function ($choice) {
                        if ($choice === null) {
                            return '';
                        } elseif (is_string($choice)) {
                            return $choice;
                        } else {
                            return (string)$choice->getAffilid();
                        }
                    },
                    'choice_attr' => function ($choice, $key, $value) {
                        if ($choice === 'new') {
                            // use 'data-id' instead of 'id' because of Symfony issue #20965
                            return ['data-id' => 'user_registration_affiliation_new'];
                        }
                        return [];
                    },
                    'constraints' => [
                        new NotBlank(),
                    ],
                ]);
            if ($this->config->get('show_new_affiliation_option')) {
                $builder->add('affiliationName', TextType::class, [
                    'label' => false,
                    'required' => false,
                    'attr' => [
                        'placeholder' => 'Affiliation name',
                    ],
                    'mapped' => false,
                ]);
                if ($this->config->get('show_flags')) {
                    $builder->add('affiliationCountry', ChoiceType::class, [
                        'label' => false,
                        'required' => false,
                        'mapped' => false,
                        'choices' => $countries,
                        'placeholder' => 'No country',
                    ]);
                }
            }
        }

        $builder
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'invalid_message' => 'The password fields must match.',
                'first_options' => [
                    'label' => false,
                    'attr' => [
                        'placeholder' => 'Password',
                        'autocomplete' => 'new-password',
                    ],
                ],
                'second_options' => [
                    'label' => false,
                    'attr' => [
                        'placeholder' => 'Repeat Password',
                        'autocomplete' => 'new-password',
                    ],
                ],
                'mapped' => false,
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Register',
                'attr' => [
                    'class' => 'btn btn-lg btn-primary btn-block',
                ],
            ]);

    }

    /**
     * @inheritDoc
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $validateAffiliation = function ($data, ExecutionContext $context) {
            if ($this->config->get('show_affiliations')) {
                /** @var Form $form */
                $form = $context->getRoot();
                if ($form->get('affiliation')->getData() === 'new') {
                    $affiliationName = $form->get('affiliationName')->getData();
                    if (empty($affiliationName)) {
                        $context->buildViolation('This value should not be blank.')
                            ->atPath('affiliationName')
                            ->addViolation();
                    }
                    if ($this->em->getRepository(TeamAffiliation::class)->findOneBy(['name' => $affiliationName])) {
                        $context->buildViolation('This affiliation name is already in use.')
                            ->atPath('affiliationName')
                            ->addViolation();
                    }
                }
            }
        };
        $resolver->setDefaults(
            [
                'data_class' => User::class,
                'constraints' => new Callback($validateAffiliation)
            ]
        );
    }
}
