<?php declare(strict_types=1);

namespace App\Form\Type;

use App\Entity\ContestSite;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ContestSiteType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('name');
        $builder->add('sortorder', IntegerType::class);
        $builder->add('active', ChoiceType::class, [
            'expanded' => true,
            'choices' => [
                'Yes' => true,
                'No' => false,
            ],
        ]);
        $builder->add('save', SubmitType::class);
    }


    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(['data_class' => ContestSite::class]);
    }
}
