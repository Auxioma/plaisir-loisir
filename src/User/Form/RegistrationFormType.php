<?php

declare(strict_types=1);

namespace App\User\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Formulaire d'inscription d'un nouvel utilisateur.
 *
 * LES CHAMPS SONT CEUX DE LA MAQUETTE (Figma 591:65942), pas l'inverse.
 * Avant le câblage, ce formulaire déclarait « firstName », « lastName » et une
 * confirmation de mot de passe (RepeatedType) que la maquette ne comporte pas :
 * les noms de champs postés par le HTML ne correspondaient à rien, donc
 * `$form->isSubmitted()` restait faux et AUCUN compte n'était créé.
 * L'écran affiche quatre champs — Nom & prénom, e-mail, téléphone, mot de
 * passe — plus la case des conditions générales ; on s'aligne dessus.
 *
 * `required => false` partout : le rendu HTML doit rester celui de la maquette,
 * qui ne porte aucun attribut `required`. La validation est donc entièrement
 * côté serveur (contraintes ci-dessous), et non déléguée au navigateur.
 */
final class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('fullName', TextType::class, [
                'label' => 'Nom & prénom',
                'required' => false,
                'constraints' => [
                    new Assert\NotBlank(message: 'Veuillez saisir vos nom et prénom.'),
                    new Assert\Length(
                        min: 2,
                        max: 200,
                        minMessage: 'Votre nom doit faire au moins {{ limit }} caractères.',
                    ),
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Adresse e-mail',
                'required' => false,
                'constraints' => [
                    new Assert\NotBlank(message: 'Veuillez saisir votre adresse e-mail.'),
                    new Assert\Email(message: 'Veuillez saisir une adresse e-mail valide.'),
                    new Assert\Length(max: 180),
                ],
            ])
            // Le téléphone est facultatif : la colonne `phone` de l'entité User
            // est nullable et la maquette ne marque pas ce champ obligatoire.
            ->add('phone', TelType::class, [
                'label' => 'Numéro de téléphone',
                'required' => false,
                'constraints' => [
                    new Assert\Length(max: 30),
                ],
            ])
            ->add('password', PasswordType::class, [
                'label' => 'Mot de passe',
                'required' => false,
                'constraints' => [
                    new Assert\NotBlank(message: 'Veuillez saisir un mot de passe.'),
                    new Assert\Length(
                        min: 8,
                        // 4096 : au-delà, bcrypt tronque et le hachage coûte cher ;
                        // c'est la borne recommandée par Symfony.
                        max: 4096,
                        minMessage: 'Le mot de passe doit faire au moins {{ limit }} caractères.',
                    ),
                ],
            ])
            ->add('agreeTerms', CheckboxType::class, [
                'label' => "J'accepte les conditions générales",
                'required' => false,
                'constraints' => [
                    new Assert\IsTrue(message: 'Vous devez accepter les conditions générales et la politique de confidentialité.'),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            // Pas de data_class : le formulaire n'est pas mappé sur une entité.
            // Les données sont extraites dans le contrôleur et confiées au
            // RegistrationService, qui reste seul responsable de la création.
        ]);
    }
}
