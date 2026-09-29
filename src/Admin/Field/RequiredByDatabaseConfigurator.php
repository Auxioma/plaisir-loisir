<?php

declare(strict_types=1);

namespace App\Admin\Field;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ToOneOwningSideMapping;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldConfiguratorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use Symfony\Component\Validator\Constraints\NotNull;

/**
 * Back-office : un champ que la base exige est obligatoire dans le formulaire.
 *
 * Beaucoup d'entités déclarent leurs liens « facultatifs » côté PHP
 * (`?ProviderProfile $provider = null`) alors que la colonne est NOT NULL.
 * La validation automatique (auto_mapping) se fie au type PHP : elle laissait
 * donc passer un prestataire ou une catégorie vide, et la base refusait
 * ensuite l'enregistrement par une erreur 500.
 *
 * On lit ici le mapping Doctrine de chaque champ affiché : colonne ou lien
 * NOT NULL → contrainte NotNull sur le champ du formulaire. L'erreur
 * s'affiche sous le champ, la saisie est conservée.
 *
 * Seuls les champs PRÉSENTS dans le formulaire sont concernés : une colonne
 * remplie par le code (dates, identifiant, note moyenne) n'est jamais
 * bloquée, contrairement à une contrainte posée sur l'entité.
 */
final class RequiredByDatabaseConfigurator implements FieldConfiguratorInterface
{
    public function supports(FieldDto $field, EntityDto $entityDto): bool
    {
        return !str_contains($field->getProperty(), '.');
    }

    public function configure(FieldDto $field, EntityDto $entityDto, AdminContext $context): void
    {
        if (!$this->requiredByDatabase($entityDto->getClassMetadata(), $field->getProperty())) {
            return;
        }

        $constraints = $field->getFormTypeOption('constraints') ?? [];
        $constraints[] = new NotNull(message: 'Ce champ est obligatoire.');

        $field->setFormTypeOption('constraints', $constraints);

        // Une case à cocher « required » obligerait à la COCHER : décochée,
        // elle vaut false, ce qui satisfait déjà la base.
        if (BooleanField::class !== $field->getFieldFqcn()) {
            $field->setFormTypeOption('required', true);
        }
    }

    /**
     * @param ClassMetadata<object> $metadata
     */
    private function requiredByDatabase(ClassMetadata $metadata, string $property): bool
    {
        if ($metadata->hasField($property)) {
            return !$metadata->isIdentifier($property) && !$metadata->isNullable($property);
        }

        if (!$metadata->hasAssociation($property)) {
            return false;
        }

        $mapping = $metadata->getAssociationMapping($property);
        if (!$mapping instanceof ToOneOwningSideMapping) {
            return false;
        }

        foreach ($mapping->joinColumns as $joinColumn) {
            // Doctrine : une colonne de jointure est nullable par défaut.
            if (false === $joinColumn->nullable) {
                return true;
            }
        }

        return false;
    }
}
