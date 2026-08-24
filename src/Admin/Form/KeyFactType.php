<?php

declare(strict_types=1);

namespace App\Admin\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Une ligne du tableau « en bref » d'une fiche activite.
 *
 * Ces blocs sont stockes en JSON, pas en entites : `data_class` reste donc a
 * null et le formulaire travaille sur un simple tableau associatif.
 */
final class KeyFactType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('label', TextType::class, [
                'label' => 'Intitule',
                'attr' => ['placeholder' => 'Duree'],
            ])
            ->add('value', TextType::class, [
                'label' => 'Valeur',
                'attr' => ['placeholder' => '2h-3h'],
            ])
            ->add('star', CheckboxType::class, [
                'label' => 'Afficher une etoile',
                'required' => false,
                'help' => 'Reserve a la ligne des avis clients.',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => null]);
    }
}
