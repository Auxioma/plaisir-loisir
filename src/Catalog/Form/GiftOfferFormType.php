<?php

declare(strict_types=1);

namespace App\Catalog\Form;

use App\Shared\Form\PhoneNumberType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Écran « Offrir un cadeau — Vos informations » (écrans 5-7 de la maquette).
 *
 * Non mappé sur une entité, comme `RegistrationFormType` : il n'existe pas
 * encore d'entité de commande/panier cadeau (tunnel réservation non câblé,
 * cf. CLAUDE.md). Ce formulaire ne fait que porter la validation des champs
 * qui n'en avaient aucune jusqu'ici — il ne persiste toujours rien, exactement
 * comme avant.
 *
 * Les champs destinataire (expéditeur, destinataire, message) restent hors
 * de ce formulaire : ils ne sont ni validés ni persistés aujourd'hui, et ce
 * n'est pas l'objet de cette tâche.
 */
final class GiftOfferFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('fullName', TextType::class, [
                'constraints' => [
                    new Assert\NotBlank(message: 'Veuillez saisir vos nom et prénom.'),
                    new Assert\Length(max: 200),
                ],
            ])
            ->add('email', EmailType::class, [
                'constraints' => [
                    new Assert\NotBlank(message: 'Veuillez saisir votre adresse e-mail.'),
                    new Assert\Email(message: 'Veuillez saisir une adresse e-mail valide.'),
                    new Assert\Length(max: 180),
                ],
            ])
            ->add('phone', PhoneNumberType::class, [
                'required' => true,
                'not_blank_message' => 'Veuillez saisir un numéro de téléphone.',
            ])
            ->add('agreeTerms', CheckboxType::class, [
                'constraints' => [
                    new Assert\IsTrue(message: 'Vous devez accepter les conditions générales et la politique de confidentialité.'),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            // Message affiché à la place du texte générique de Symfony si le
            // jeton CSRF a expiré, pour garder le même message qu'avant
            // (l'ancienne vérification manuelle dans GiftController).
            'csrf_message' => 'Votre session a expiré, merci de recommencer la saisie.',
        ]);
    }
}
