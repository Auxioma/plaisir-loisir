<?php

declare(strict_types=1);

namespace App\Shared\Form\DataTransformer;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

/**
 * Convertit entre la donnée modèle (chaîne E.164 unique, ex. `+33612345678`,
 * ce que stocke la colonne Doctrine) et la donnée vue du `PhoneNumberType`
 * compound (`['country' => 'FR', 'number' => '06 12 34 56 78']`).
 *
 * @implements DataTransformerInterface<?string, array{country: string, number: string}>
 */
final class PhoneNumberToArrayTransformer implements DataTransformerInterface
{
    public function __construct(
        private readonly string $defaultRegion = 'FR',
    ) {
    }

    /**
     * @param ?string $value
     *
     * @return array{country: string, number: string}
     */
    public function transform(mixed $value): array
    {
        if (null === $value || '' === $value) {
            return ['country' => $this->defaultRegion, 'number' => ''];
        }

        $util = PhoneNumberUtil::getInstance();

        try {
            $number = $util->parse($value, null);
        } catch (NumberParseException) {
            // Donnée déjà en base invalide/mal formée : on l'affiche telle
            // quelle plutôt que de faire planter l'édition du formulaire.
            return ['country' => $this->defaultRegion, 'number' => $value];
        }

        return [
            'country' => $util->getRegionCodeForNumber($number) ?? $this->defaultRegion,
            'number' => $util->format($number, PhoneNumberFormat::NATIONAL),
        ];
    }

    /**
     * @param mixed $value array{country?: string, number?: string}|null
     */
    public function reverseTransform(mixed $value): ?string
    {
        if (null === $value || !\is_array($value)) {
            return null;
        }

        $number = trim((string) ($value['number'] ?? ''));

        if ('' === $number) {
            return null;
        }

        $region = '' !== ($value['country'] ?? '') ? $value['country'] : $this->defaultRegion;
        $util = PhoneNumberUtil::getInstance();

        try {
            $parsed = $util->parse($number, $region);
        } catch (NumberParseException $e) {
            throw new TransformationFailedException('Invalid phone number.', previous: $e, invalidMessage: 'Veuillez saisir un numéro de téléphone valide.');
        }

        if (!$util->isValidNumber($parsed)) {
            throw new TransformationFailedException('Invalid phone number.', invalidMessage: 'Veuillez saisir un numéro de téléphone valide.');
        }

        return $util->format($parsed, PhoneNumberFormat::E164);
    }
}
