<?php

declare(strict_types=1);

namespace App\Corporate\Form;

use App\Corporate\Entity\PartnerApplication;
use App\Shared\Form\PhoneNumberType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Formulaire « Devenir partenaire » (Figma, écran 3 du flow corporate).
 *
 * Remplace l'extraction manuelle des champs POST qu'il y avait jusqu'ici
 * dans `CorporateController::partnerForm()`. `data_class` sur
 * `PartnerApplication` : les contraintes `Assert\*` déjà posées sur
 * l'entité (siteName, siteUrl, sector, traffic, address, postalCode, email,
 * termsAccepted, et désormais `phone`) suffisent, pas besoin de les
 * dupliquer ici.
 *
 * Portée volontairement limitée au téléphone : secteur, trafic et ville
 * restent HORS de ce formulaire, exactement comme avant. Ce sont des
 * `<select>` dans la maquette dont les options n'ont jamais été câblées côté
 * template (défaut préexistant, hors sujet de cette tâche) — le contrôleur
 * continue de recopier leur valeur brute sur l'entité séparément.
 */
final class PartnerApplicationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('siteName', TextType::class)
            ->add('siteUrl', TextType::class)
            ->add('companyName', TextType::class, ['required' => false])
            ->add('contactName', TextType::class, ['required' => false])
            ->add('phone', PhoneNumberType::class, [
                'required' => false,
            ])
            ->add('address', TextType::class)
            ->add('postalCode', TextType::class)
            ->add('email', EmailType::class)
            ->add('description', TextareaType::class, ['required' => false])
            ->add('termsAccepted', CheckboxType::class)
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PartnerApplication::class,
            // Garde le même message qu'avant (ancienne vérification CSRF
            // manuelle dans CorporateController), à la place du texte
            // générique de Symfony.
            'csrf_message' => 'Votre session a expiré, merci de renvoyer le formulaire.',
        ]);
    }
}
