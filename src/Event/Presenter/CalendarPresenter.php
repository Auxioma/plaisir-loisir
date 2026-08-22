<?php

declare(strict_types=1);

namespace App\Event\Presenter;

use App\Event\Entity\Event;

/**
 * Construit la grille mensuelle du calendrier des événements.
 *
 * L'ancienne grille était écrite à la main dans le code, et elle sautait le
 * 19 : la quatrième rangée passait de 18 à 20. Une grille calculée ne peut pas
 * commettre cette erreur.
 *
 * Les semaines commencent le LUNDI, comme l'en-tête de la maquette l'annonce.
 * La grille déborde sur les mois voisins pour remplir les rangées : ces
 * cellules sont marquées « hors mois » et affichées en gris.
 */
final class CalendarPresenter
{
    public function __construct(
        private readonly EventPresenter $eventPresenter,
    ) {
    }

    /**
     * @param iterable<Event> $events événements du mois affiché, bornes comprises
     *
     * @return list<list<array{day: int, out: bool, today: bool, events: list<array<string, mixed>>}>>
     */
    public function grid(\DateTimeImmutable $month, iterable $events): array
    {
        $premier = $month->modify('first day of this month')->setTime(0, 0);

        // « N » vaut 1 pour lundi : on recule d'autant de jours pour tomber sur
        // le lundi de la première semaine affichée.
        $debut = $premier->modify('-'.((int) $premier->format('N') - 1).' days');

        $parJour = $this->groupByDay($events);
        $aujourdhui = (new \DateTimeImmutable())->format('Y-m-d');
        $moisAffiche = $premier->format('Y-m');

        $grille = [];

        // Six rangées couvrent tous les cas de figure — un mois de 31 jours
        // commençant un dimanche en occupe six. La dernière est retirée si
        // elle ne contient que le mois suivant, pour ne pas ajouter une rangée
        // vide là où la maquette n'en montre pas.
        for ($semaine = 0; $semaine < 6; ++$semaine) {
            $rangee = [];

            for ($jour = 0; $jour < 7; ++$jour) {
                $date = $debut->modify(sprintf('+%d days', $semaine * 7 + $jour));
                $cle = $date->format('Y-m-d');

                $rangee[] = [
                    'day' => (int) $date->format('j'),
                    'out' => $date->format('Y-m') !== $moisAffiche,
                    'today' => $cle === $aujourdhui,
                    'events' => $parJour[$cle] ?? [],
                ];
            }

            $grille[] = $rangee;
        }

        return $this->trimTrailingWeek($grille);
    }

    /**
     * Libellé du mois affiché : « Juillet 2026 ».
     */
    public function monthLabel(\DateTimeImmutable $month): string
    {
        $formatted = (string) \IntlDateFormatter::formatObject($month, 'LLLL y', \Locale::getDefault());

        return mb_strtoupper(mb_substr($formatted, 0, 1)).mb_substr($formatted, 1);
    }

    /**
     * Range les événements par jour, dans la forme attendue par la pastille.
     *
     * @param iterable<Event> $events
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private function groupByDay(iterable $events): array
    {
        $parJour = [];

        foreach ($events as $event) {
            $carte = $this->eventPresenter->card($event);
            $local = $event->getStartsAt()->setTimezone(new \DateTimeZone(date_default_timezone_get()));

            $parJour[$local->format('Y-m-d')][] = [
                'slug' => $carte['slug'],
                'title' => $carte['title'],
                'hours' => $carte['hours'],
                'color' => $carte['color'],
            ];
        }

        return $parJour;
    }

    /**
     * Retire la dernière rangée si elle est entièrement hors du mois.
     *
     * @param list<list<array{day: int, out: bool, today: bool, events: list<array<string, mixed>>}>> $grille
     *
     * @return list<list<array{day: int, out: bool, today: bool, events: list<array<string, mixed>>}>>
     */
    private function trimTrailingWeek(array $grille): array
    {
        $derniere = end($grille);

        if (false === $derniere) {
            return $grille;
        }

        foreach ($derniere as $cellule) {
            if (!$cellule['out']) {
                return $grille;
            }
        }

        array_pop($grille);

        return $grille;
    }
}
