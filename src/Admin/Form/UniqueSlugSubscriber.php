<?php

declare(strict_types=1);

namespace App\Admin\Form;

use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * Rend l'adresse (slug) unique AVANT la validation du formulaire.
 *
 * Deux activités peuvent légitimement porter le même titre (« Balade en
 * bateau au coucher du soleil » chez deux prestataires) ; elles ne peuvent
 * pas partager la même adresse. Le champ SlugField recopie le titre : créer
 * un doublon de titre produisait donc une adresse déjà prise, et la base la
 * refusait par une erreur 500. On ajoute un suffixe (-2, -3…), comme le font
 * déjà les événements et les groupes (EventDraftService::uniqueSlug).
 *
 * Écouté sur SUBMIT : les champs sont déjà recopiés dans l'entité, et la
 * validation (POST_SUBMIT) n'a pas encore eu lieu.
 *
 * Les lignes supprimées en douceur (deletedAt) gardent leur adresse dans
 * l'index unique : aucun filtre ne les masque ici, elles comptent donc bien.
 *
 * Pas un service : chaque CRUD l'instancie avec son propre champ source.
 */
#[Exclude]
final class UniqueSlugSubscriber implements EventSubscriberInterface
{
    /**
     * @param string                                $sourceField champ d'où tirer l'adresse si elle est vide (titre, nom)
     * @param (\Closure(string, string): void)|null $onRenamed   prévenu quand l'adresse demandée était prise
     */
    public function __construct(
        private readonly ManagerRegistry $doctrine,
        private readonly string $sourceField,
        private readonly ?\Closure $onRenamed = null,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [FormEvents::SUBMIT => 'onSubmit'];
    }

    public function onSubmit(FormEvent $event): void
    {
        $entity = $event->getData();
        if (!\is_object($entity) || !method_exists($entity, 'getSlug') || !method_exists($entity, 'setSlug')) {
            return;
        }

        $wanted = $this->currentSlug($entity);
        if ('' === $wanted) {
            $source = $this->read($entity, $this->sourceField);
            if ('' === $source) {
                // Ni adresse ni titre : la validation signalera le champ vide.
                return;
            }
            $wanted = strtolower((new AsciiSlugger('fr'))->slug($source)->toString());
        }

        $slug = $this->firstFreeSlug($entity, $wanted);
        $entity->setSlug($slug);

        if ($slug !== $wanted && null !== $this->onRenamed) {
            ($this->onRenamed)($wanted, $slug);
        }
    }

    private function firstFreeSlug(object $entity, string $base): string
    {
        $class = $entity::class;
        $manager = $this->doctrine->getManagerForClass($class);
        if (null === $manager) {
            return $base;
        }

        $repository = $manager->getRepository($class);
        $ownId = method_exists($entity, 'getId') ? $entity->getId() : null;

        $slug = $base;
        $suffix = 2;
        while (null !== ($other = $repository->findOneBy(['slug' => $slug])) && $other !== $entity && (null === $ownId || !$this->sameId($other, $ownId))) {
            $slug = $base.'-'.$suffix;
            ++$suffix;
        }

        return $slug;
    }

    private function sameId(object $other, mixed $ownId): bool
    {
        if (!method_exists($other, 'getId')) {
            return false;
        }

        return (string) $other->getId() === (string) $ownId;
    }

    private function currentSlug(object $entity): string
    {
        try {
            return trim((string) $entity->getSlug());
        } catch (\Error) {
            // Propriété typée non initialisée (nouvelle entité, champ vide).
            return '';
        }
    }

    private function read(object $entity, string $field): string
    {
        $getter = 'get'.ucfirst($field);
        if (!method_exists($entity, $getter)) {
            return '';
        }

        try {
            return trim((string) $entity->{$getter}());
        } catch (\Error) {
            return '';
        }
    }
}
