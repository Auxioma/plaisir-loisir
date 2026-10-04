<?php

declare(strict_types=1);

namespace App\User\Form;

use App\Shared\Form\PhoneNumberType;
use App\User\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CountryType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * « Informations personnelles » de l'espace compte (maquette
 * profil_infos_particulier, 30/09) : identité, photo, coordonnées, adresse.
 *
 * Anciennement AccountSettingsFormType (Lot I, 15/09), qui vivait sur l'écran
 * Paramètres : celui-ci ne garde plus que la sécurité et la gestion du compte.
 *
 * L'adresse n'est pas mappée : elle vit dans l'entité Address (première
 * adresse de l'utilisateur), que le contrôleur crée ou met à jour.
 */
final class PersonalInformationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, [
                'label' => 'Prénom',
                'constraints' => [
                    new Assert\NotBlank(message: 'Veuillez saisir votre prénom.'),
                    new Assert\Length(max: 100),
                ],
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Nom',
                'constraints' => [
                    new Assert\NotBlank(message: 'Veuillez saisir votre nom.'),
                    new Assert\Length(max: 100),
                ],
            ])
            ->add('birthDate', DateType::class, [
                'label' => 'Date de naissance',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'required' => false,
                'constraints' => [new Assert\LessThan('today', message: 'La date de naissance doit être passée.')],
            ])
            ->add('gender', ChoiceType::class, [
                'label' => 'Genre',
                'required' => false,
                'placeholder' => 'Non précisé',
                'choices' => ['Femme' => 'femme', 'Homme' => 'homme', 'Autre' => 'autre'],
            ])
            ->add('preferredLocale', ChoiceType::class, [
                'label' => 'Langue',
                'required' => false,
                'placeholder' => false,
                'choices' => ['Français' => 'fr', 'English' => 'en'],
            ])
            ->add('country', CountryType::class, [
                'label' => 'Pays',
                'required' => false,
                'placeholder' => 'Choisir un pays',
                'preferred_choices' => ['FR', 'BE', 'CH', 'LU', 'CA'],
            ])
            ->add('phone', PhoneNumberType::class, [
                'label' => 'Téléphone',
                'required' => false,
            ])
            ->add('addressLine1', TextType::class, ['label' => 'Adresse', 'required' => false, 'mapped' => false, 'constraints' => [new Assert\Length(max: 255)]])
            ->add('addressLine2', TextType::class, ['label' => 'Complément d’adresse (optionnel)', 'required' => false, 'mapped' => false, 'constraints' => [new Assert\Length(max: 255)]])
            ->add('postalCode', TextType::class, ['label' => 'Code postal', 'required' => false, 'mapped' => false, 'constraints' => [new Assert\Length(max: 20)]])
            ->add('city', TextType::class, ['label' => 'Ville', 'required' => false, 'mapped' => false, 'constraints' => [new Assert\Length(max: 120)]])
            ->add('addressCountry', CountryType::class, ['label' => 'Pays', 'required' => false, 'mapped' => false, 'placeholder' => 'Choisir un pays', 'preferred_choices' => ['FR', 'BE', 'CH', 'LU', 'CA']])
            // Non mappé sur l'entité : l'upload est géré à part par
            // AvatarStorageService, comme le mot de passe l'est déjà dans
            // RegistrationFormType.
            ->add('photo', FileType::class, [
                'label' => 'Photo de profil',
                'required' => false,
                'mapped' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            // Garde le même message qu'avant (ancienne vérification CSRF
            // manuelle dans AccountController), à la place du texte
            // générique de Symfony.
            'csrf_message' => 'Votre session a expiré, merci de réessayer.',
        ]);
    }
}
