<?php

declare(strict_types=1);

namespace App\Provider\Form;

use App\Catalog\Entity\Category;
use App\Catalog\Repository\CategoryRepository;
use App\Shared\Form\PhoneNumberType;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Étape 1/2 de l'inscription professionnelle — « Informations générales ».
 *
 * LES CHAMPS SONT CEUX DE LA MAQUETTE (Figma 955:91899), plus le mot de passe.
 * La maquette n'en comportait pas : le professionnel se serait donc inscrit
 * sans jamais pouvoir se connecter à l'écran de connexion voisin, qui en
 * réclame un. Le CTO a tranché — on l'ajoute, dans la grille existante.
 *
 * `required` HTML actif sur les champs obligatoires, comme sur l'inscription
 * client : le navigateur bloque désormais l'envoi et signale les champs
 * vides avant même d'atteindre le serveur, en plus des contraintes
 * ci-dessous qui restent la validation de référence.
 */
final class ProviderRegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('lastName', TextType::class, [
                'label' => 'Nom',
                'constraints' => [
                    new Assert\NotBlank(message: 'Veuillez saisir votre nom.'),
                    new Assert\Length(max: 100),
                ],
            ])
            ->add('firstName', TextType::class, [
                'label' => 'Prénom',
                'constraints' => [
                    new Assert\NotBlank(message: 'Veuillez saisir votre prénom.'),
                    new Assert\Length(max: 100),
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Adresse e-mail',
                'constraints' => [
                    new Assert\NotBlank(message: 'Veuillez saisir votre adresse e-mail.'),
                    new Assert\Email(message: 'Veuillez saisir une adresse e-mail valide.'),
                    new Assert\Length(max: 180),
                ],
            ])
            // Obligatoire ici, contrairement à l'inscription client : un
            // dossier professionnel se vérifie par téléphone, et le service
            // client de la maquette annonce qu'il rappellera.
            ->add('phone', PhoneNumberType::class, [
                'label' => 'Numéro de téléphone',
                'required' => true,
                'not_blank_message' => 'Veuillez saisir un numéro de téléphone : notre service client vous y joindra.',
            ])
            ->add('password', PasswordType::class, [
                'label' => 'Mot de passe',
                'constraints' => [
                    new Assert\NotBlank(message: 'Veuillez saisir un mot de passe.'),
                    new Assert\Length(
                        min: 8,
                        // 4096 : borne recommandée par Symfony, au-delà bcrypt
                        // tronque et le hachage coûte cher.
                        max: 4096,
                        minMessage: 'Le mot de passe doit faire au moins {{ limit }} caractères.',
                    ),
                ],
            ])
            ->add('mainCategory', EntityType::class, [
                'label' => "Choix de l'activité",
                'class' => Category::class,
                'choice_label' => 'name',
                'placeholder' => 'Sélectionnez votre activité',
                // Seules les rubriques de premier niveau : la maquette montre
                // une liste courte, et un prestataire se déclare « Sport » ou
                // « Gastronomie », pas « Kayak biplace en eau vive ».
                'query_builder' => static fn (CategoryRepository $repository): QueryBuilder => $repository
                    ->createQueryBuilder('c')
                    ->andWhere('c.parent IS NULL')
                    ->orderBy('c.position', 'ASC')
                    ->addOrderBy('c.name', 'ASC'),
                'constraints' => [
                    new Assert\NotNull(message: 'Veuillez choisir votre activité principale.'),
                ],
            ])
            ->add('registeredOffice', TextType::class, [
                'label' => 'Adresse du siège social',
                'constraints' => [
                    new Assert\NotBlank(message: 'Veuillez indiquer l\'adresse de votre siège social.'),
                    new Assert\Length(max: 255),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            // Pas de data_class : le contrôleur extrait les valeurs et confie
            // la création à ProviderRegistrationService, seul responsable.
        ]);
    }
}
