<?php

declare(strict_types=1);

namespace App\Event\Service;

use App\Event\Entity\Event;
use App\Event\Repository\EventCategoryRepository;
use App\Event\Repository\EventRepository;
use App\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Le brouillon d'événement en cours de saisie dans l'assistant.
 *
 * POURQUOI LA SESSION ET NON UNE LIGNE EN BASE
 * L'assistant compte huit étapes. Enregistrer une ligne dès la première
 * peuplerait la table d'événements abandonnés en cours de route, qu'il
 * faudrait ensuite distinguer des vrais et nettoyer. La session porte le
 * brouillon ; la base ne reçoit que ce qui est publié.
 *
 * Le revers est assumé : fermer son navigateur perd la saisie. Le bouton
 * « Enregistrer en brouillon » de la maquette servira à cela — il n'est pas
 * encore branché.
 */
final class EventDraftService
{
    private const SESSION_KEY = 'event_draft';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EventRepository $events,
        private readonly EventCategoryRepository $categories,
        private readonly SluggerInterface $slugger,
    ) {
    }

    /**
     * Ajoute au brouillon ce que l'étape vient d'envoyer.
     *
     * Les champs absents de l'étape ne sont PAS effacés : chaque écran n'en
     * porte qu'une partie, et un envoi ne doit pas vider ce qui a été saisi
     * ailleurs.
     *
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

            // Une valeur vide n'ecrase pas une valeur deja saisie : l'etape 2
            // reaffiche le titre de l'etape 1, un envoi a vide l'effacerait.
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

    public function clear(SessionInterface $session): void
    {
        $session->remove(self::SESSION_KEY);
    }

    /**
     * Publie l'événement à partir du brouillon.
     *
     * @return array{0: Event|null, 1: list<string>} l'événement créé, ou les erreurs
     */
    public function publish(SessionInterface $session, ?User $organizer): array
    {
        $draft = $this->current($session);
        $titre = $draft['titre'] ?? '';

        if ('' === $titre) {
            return [null, ['Votre événement doit avoir un titre. Revenez à la première étape.']];
        }

        $debut = $this->toDateTime($draft['date_debut'] ?? null, $draft['heure_debut'] ?? null);

        if (null === $debut) {
            return [null, ['La date de début est absente ou illisible. Revenez à la deuxième étape.']];
        }

        $fin = $this->toDateTime($draft['date_fin'] ?? null, $draft['heure_fin'] ?? null);

        if (null !== $fin && $fin < $debut) {
            return [null, ['La date de fin précède la date de début.']];
        }

        $event = new Event();
        $event
            ->setTitle($titre)
            ->setSlug($this->uniqueSlug($titre))
            ->setCategory($this->categories->findOneBy(['name' => $draft['categorie'] ?? '']))
            ->setOrganizer($organizer)
            ->setLocation($draft['lieu'] ?? null)
            ->setStartsAt($debut)
            ->setEndsAt($fin)
            // « private » et « group » sont tous deux hors du listing public ;
            // la distinction entre les deux demandera l'appartenance a un
            // groupe, qui n'existe pas encore.
            ->setPrivate(\in_array($draft['visibilite'] ?? 'public', ['private', 'group'], true))
            // Les evenements crees passent APRES ceux de la maquette, qui
            // occupent les rangs 0 a 11.
            ->setPosition(100);

        $this->entityManager->persist($event);
        $this->entityManager->flush();

        $this->clear($session);

        return [$event, []];
    }

    /**
     * « 24 / 05 / 2026 » + « 18:00 » -> objet date.
     *
     * Le format vient de la maquette, espaces autour des barres obliques
     * compris. On les retire avant lecture : un utilisateur qui saisit
     * « 24/05/2026 » sans espaces doit être compris lui aussi.
     */
    private function toDateTime(?string $date, ?string $heure): ?\DateTimeImmutable
    {
        if (null === $date || '' === trim($date)) {
            return null;
        }

        $date = str_replace(' ', '', $date);
        $heure = null !== $heure && '' !== trim($heure) ? trim($heure) : '00:00';

        $resultat = \DateTimeImmutable::createFromFormat('d/m/Y H:i', $date.' '.$heure);

        return false !== $resultat ? $resultat : null;
    }

    /**
     * Adresse unique : deux événements peuvent porter le même titre, pas la
     * même adresse.
     */
    private function uniqueSlug(string $titre): string
    {
        $base = strtolower($this->slugger->slug($titre)->toString());
        $slug = $base;
        $suffixe = 2;

        while (null !== $this->events->findOneBySlug($slug)) {
            $slug = $base.'-'.$suffixe;
            ++$suffixe;
        }

        return $slug;
    }
}
