<?php

declare(strict_types=1);

namespace App\Admin\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Une ligne « intitule / valeur » du bloc rendez-vous.
 */
final class LabelValueType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('label', TextType::class, [
                'label' => 'Intitule',
                'attr' => ['placeholder' => 'Point de depart'],
            ])
            ->add('value', TextType::class, [
                'label' => 'Valeur',
                'attr' => ['placeholder' => 'Base nautique du lac'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => null]);
    }
}
