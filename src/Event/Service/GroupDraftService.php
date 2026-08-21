<?php

declare(strict_types=1);

namespace App\Event\Service;

use App\Event\Entity\Group;
use App\Event\Repository\GroupRepository;
use App\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Le brouillon de groupe en cours de saisie dans l'assistant.
 *
 * Même raisonnement que pour les événements : le brouillon vit en session, la
 * base ne reçoit que ce qui est publié. Créer une ligne dès la première étape
 * peuplerait la table de groupes abandonnés en chemin.
 */
final class GroupDraftService
{
    private const SESSION_KEY = 'group_draft';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly GroupRepository $groups,
        private readonly SluggerInterface $slugger,
    ) {
    }

    /**
     * @param array<string, mixed> $submitted
     */
    public function merge(SessionInterface $session, array $submitted): void
    {
        /** @var array<string, string> $draft */
        $draft = $session->get(self::SESSION_KEY, []);

        foreach ($submitted as $key => $value) {
            if (!\is_string($value)) {
                continue;
            }

            $value = trim($value);

            // Une valeur vide n'ecrase pas une valeur deja saisie.
            if ('' !== $value) {
                $draft[$key] = $value;
            }
        }

        $session->set(self::SESSION_KEY, $draft);
    }

    /**
     * @return array<string, string>
     */
    public function current(SessionInterface $session): array
    {
        /** @var array<string, string> $draft */
        $draft = $session->get(self::SESSION_KEY, []);

        return $draft;
    }

    /**
     * Publie le groupe.
     *
     * @return array{0: Group|null, 1: list<string>}
     */
    public function publish(SessionInterface $session, ?User $owner): array
    {
        $draft = $this->current($session);
        $nom = $draft['nom'] ?? '';

        if ('' === $nom) {
            return [null, ['Votre groupe doit avoir un nom. Revenez à la troisième étape.']];
        }

        $group = new Group();
        $group
            ->setName($nom)
            ->setSlug($this->uniqueSlug($nom))
            ->setDescription($draft['description'] ?? null)
            ->setLocation($draft['lieu'] ?? null)
            ->setOwner($owner)
            // Un groupe naissant compte son createur, et lui seul.
            ->setMembersCount(null !== $owner ? 1 : 0)
            // Les groupes crees passent APRES les seize de la maquette, qui
            // occupent les rangs 0 a 15.
            ->setPosition(100);

        $this->entityManager->persist($group);
        $this->entityManager->flush();

        $session->remove(self::SESSION_KEY);

        return [$group, []];
    }

    private function uniqueSlug(string $nom): string
    {
        $base = strtolower($this->slugger->slug($nom)->toString());
        $slug = $base;
        $suffixe = 2;

        while (null !== $this->groups->findOneBySlug($slug)) {
            $slug = $base.'-'.$suffixe;
            ++$suffixe;
        }

        return $slug;
    }
}
