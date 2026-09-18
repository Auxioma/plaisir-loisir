<?php

declare(strict_types=1);

namespace App\Shared\Form;

use App\Shared\Form\DataTransformer\PhoneNumberToArrayTransformer;
use App\Shared\Validator\ValidPhoneNumber;
use libphonenumber\PhoneNumberUtil;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Intl\Countries;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Champ téléphone réutilisable : un sélecteur de pays (indicatif) + un
 * numéro national. La donnée manipulée par le formulaire (« model data »)
 * reste une simple chaîne E.164 (`+33612345678`), grâce au
 * `PhoneNumberToArrayTransformer` — compatible telle quelle avec les
 * colonnes `?string` existantes (`User::$phone`, `PartnerApplication::$phone`)
 * et tous les `$form->get('phone')->getData()` déjà en place.
 *
 * Un numéro mal formé ne rend jamais le formulaire « synchronisé » (le
 * transformer lève `TransformationFailedException`) : c'est ce qui porte le
 * message d'erreur de format. Les contraintes ci-dessous (`NotBlank`,
 * `ValidPhoneNumber`) protègent en plus le cas d'un champ requis laissé vide
 * et servent de garde-fou si ce type est un jour alimenté autrement qu'en
 * passant par la vue (ex. données par défaut invalides).
 *
 * Rendu : PAS de thème Twig imposé. Chaque écran rend `form.phone.country`
 * et `form.phone.number` avec son propre balisage (voir les templates
 * concernés), pour respecter la maquette Figma de chaque écran.
 */
final class PhoneNumberType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('country', ChoiceType::class, [
                'label' => false,
                'choices' => self::countryChoices($options['default_region']),
                'required' => true,
                'placeholder' => false,
            ])
            ->add('number', TelType::class, [
                'label' => false,
                'required' => $options['required'],
            ])
            ->addModelTransformer(new PhoneNumberToArrayTransformer($options['default_region']))
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'default_region' => 'FR',
            'invalid_message' => 'Veuillez saisir un numéro de téléphone valide.',
            // Message du `NotBlank` ajouté quand `required` vaut true (voir
            // le normaliseur ci-dessous). Un formulaire consommateur qui veut
            // un message différent le personnalise via cette option plutôt
            // que via `constraints` : ce dernier est déjà géré ici, et un
            // `NotBlank` supplémentaire passé par le consommateur s'ajouterait
            // au lieu de le remplacer (deux messages d'erreur pour un seul
            // champ vide).
            'not_blank_message' => 'Veuillez saisir un numéro de téléphone.',
            'compound' => true,
        ]);

        $resolver->setNormalizer('constraints', static function (Options $options, array $constraints): array {
            if ($options['required']) {
                $constraints[] = new Assert\NotBlank(message: $options['not_blank_message']);
            }

            $constraints[] = new ValidPhoneNumber(defaultRegion: $options['default_region']);

            return $constraints;
        });

        $resolver->setAllowedTypes('default_region', 'string');
        $resolver->setAllowedTypes('not_blank_message', 'string');
    }

    public function getParent(): string
    {
        return FormType::class;
    }

    /**
     * @return array<string, string>
     */
    private static function countryChoices(string $defaultRegion): array
    {
        $util = PhoneNumberUtil::getInstance();
        $names = Countries::getNames('fr');

        $choices = [];

        foreach ($util->getSupportedRegions() as $region) {
            $callingCode = $util->getCountryCodeForRegion($region);

            if ($callingCode <= 0 || !isset($names[$region])) {
                continue;
            }

            $choices[\sprintf('%s (+%d)', $names[$region], $callingCode)] = $region;
        }

        ksort($choices, \SORT_NATURAL | \SORT_FLAG_CASE);

        if (isset($names[$defaultRegion])) {
            $defaultLabel = \sprintf('%s (+%d)', $names[$defaultRegion], $util->getCountryCodeForRegion($defaultRegion));

            if (isset($choices[$defaultLabel])) {
                unset($choices[$defaultLabel]);
                $choices = [$defaultLabel => $defaultRegion] + $choices;
            }
        }

        return $choices;
    }
}
