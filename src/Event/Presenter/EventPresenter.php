<?php

declare(strict_types=1);

namespace App\Event\Presenter;

use App\Event\Entity\Event;

/**
 * Traduit une entité Event en la forme de tableau que les gabarits attendent,
 * comme ActivityPresenter le fait pour le catalogue.
 *
 * Forme produite, identique à StaticEvents::events() :
 * title, category, color, image, location, hours, participants, date.
 */
final class EventPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function card(Event $event): array
    {
        return [
            'slug' => $event->getSlug(),
            'title' => $event->getTitle(),
            'category' => $event->getCategory()?->getName() ?? '',
            'color' => $event->getCategory()?->getColor() ?? 'blue',
            'image' => $event->getImagePath(),
            'location' => $event->getLocation(),
            'hours' => $this->hours($event),
            // La maquette écrit le nombre entre guillemets (« 12 ») : on le
            // rend en chaîne pour que les gabarits reçoivent exactement ce
            // qu'ils recevaient.
            'participants' => (string) $event->getParticipantsCount(),
            'date' => $this->date($event),
        ];
    }

    /**
     * @param iterable<Event> $events
     *
     * @return list<array<string, mixed>>
     */
    public function cards(iterable $events): array
    {
        $cards = [];

        foreach ($events as $event) {
            $cards[] = $this->card($event);
        }

        return $cards;
    }

    /**
     * « 15 Mai 2026 ».
     *
     * Le mois porte une majuscule dans la maquette, alors que le français
     * l'écrit en minuscule et que l'ICU la produit ainsi. On la remet donc,
     * plutôt que de figer douze noms de mois dans le code.
     */
    private function date(Event $event): string
    {
        $formatted = (string) \IntlDateFormatter::formatObject(
            $this->local($event->getStartsAt()),
            'dd LLLL y',
            \Locale::getDefault(),
        );

        return (string) preg_replace_callback(
            '/\p{L}[\p{L}\p{M}]*/u',
            static fn (array $mot): string => mb_strtoupper(mb_substr($mot[0], 0, 1)).mb_substr($mot[0], 1),
            $formatted,
        );
    }

    /**
     * « 9h00 - 16h00 », sans zéro devant l'heure.
     *
     * Un événement sans heure de fin n'affiche que son heure de début : mieux
     * vaut une information incomplète qu'un tiret suivi de rien.
     */
    private function hours(Event $event): string
    {
        $debut = $this->local($event->getStartsAt())->format('G\hi');
        $fin = $event->getEndsAt();

        return null !== $fin ? $debut.' - '.$this->local($fin)->format('G\hi') : $debut;
    }

    /**
     * Ramene une date dans le fuseau d'affichage.
     *
     * INDISPENSABLE : la colonne est un TIMESTAMPTZ, que PostgreSQL rend en
     * UTC. Un evenement enregistre a 9h00 heure de Paris revenait a 7h00, et
     * toutes les heures de la maquette etaient decalees de deux heures. Une
     * date proche de minuit aurait meme change de jour.
     */
    private function local(\DateTimeImmutable $date): \DateTimeImmutable
    {
        return $date->setTimezone(new \DateTimeZone(date_default_timezone_get()));
    }
}
