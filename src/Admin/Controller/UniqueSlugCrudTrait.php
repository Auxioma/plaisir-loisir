<?php

declare(strict_types=1);

namespace App\Admin\Controller;

use App\Admin\Form\UniqueSlugSubscriber;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Pour les rubriques dont l'entité a une adresse (slug) unique : un titre
 * déjà employé donne « mon-titre-2 » au lieu d'une erreur à l'enregistrement.
 * Voir UniqueSlugSubscriber.
 *
 * Le contrôleur indique le champ d'où l'adresse est tirée (titre, nom).
 */
trait UniqueSlugCrudTrait
{
    abstract protected function slugSourceField(): string;

    public function createNewFormBuilder(EntityDto $entityDto, KeyValueStore $formOptions, AdminContext $context): FormBuilderInterface
    {
        return $this->withUniqueSlug(parent::createNewFormBuilder($entityDto, $formOptions, $context));
    }

    public function createEditFormBuilder(EntityDto $entityDto, KeyValueStore $formOptions, AdminContext $context): FormBuilderInterface
    {
        return $this->withUniqueSlug(parent::createEditFormBuilder($entityDto, $formOptions, $context));
    }

    private function withUniqueSlug(FormBuilderInterface $builder): FormBuilderInterface
    {
        $builder->addEventSubscriber(new UniqueSlugSubscriber(
            $this->container->get('doctrine'),
            $this->slugSourceField(),
            function (string $wanted, string $given): void {
                $this->addFlash('info', sprintf('L\'adresse « %s » était déjà prise : « %s » a été utilisée à la place.', $wanted, $given));
            },
        ));

        return $builder;
    }
}
