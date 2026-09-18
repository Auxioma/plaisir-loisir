<?php

declare(strict_types=1);

namespace App\User\Form;

use App\Shared\Form\PhoneNumberType;
use App\User\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Paramètres du compte (Lot I, 15/09) — remplace l'extraction manuelle des
 * champs POST qu'il y avait jusqu'ici dans `AccountController::settings()`
 * (aucune validation de format, juste un `trim()` + une troncature).
 *
 * Aucune maquette Figma pour cet écran : contrairement aux formulaires
 * d'inscription, rien n'impose de garder un balisage particulier.
 */
final class AccountSettingsFormType extends AbstractType
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
            ->add('phone', PhoneNumberType::class, [
                'label' => 'Téléphone',
                'required' => false,
            ])
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
