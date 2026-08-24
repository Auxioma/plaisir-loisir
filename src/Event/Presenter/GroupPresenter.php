<?php

declare(strict_types=1);

namespace App\Event\Presenter;

use App\Event\Entity\Group;
use App\Event\Entity\GroupAlbum;

/**
 * Traduit un groupe et ses albums en la forme attendue par les gabarits.
 *
 * Formes produites, identiques à StaticEvents :
 *  - groupe : name, image, description, location, members, badge
 *  - album  : title, location, image, photos, updated
 */
final class GroupPresenter
{
    /** Meme repli que les listings : un album sans photo ne doit pas faire tomber l'onglet. */
    private const FALLBACK_ALBUM_IMAGE = 'images/events/alb-canoerouge.jpg';

    /**
     * Abréviations de mois telles que la maquette les écrit.
     *
     * Elles ne suivent pas la norme : l'ICU abrège juillet en « juil. », la
     * maquette écrit « Juill. ». Seul juillet y figure ; les onze autres
     * suivent la même façon de faire, à confirmer quand d'autres dates
     * apparaîtront.
     *
     * @var array<int, string>
     */
    private const MOIS = [
        1 => 'Janv.', 2 => 'Févr.', 3 => 'Mars', 4 => 'Avr.', 5 => 'Mai', 6 => 'Juin',
        7 => 'Juill.', 8 => 'Août', 9 => 'Sept.', 10 => 'Oct.', 11 => 'Nov.', 12 => 'Déc.',
    ];

    /**
     * @return array<string, mixed>
     */
    public function card(Group $group): array
    {
        return [
            'slug' => $group->getSlug(),
            'name' => $group->getName(),
            'image' => $group->getImagePath(),
            'description' => $group->getDescription(),
            'location' => $group->getLocation(),
            // La maquette passe le nombre en chaîne : on le rend tel quel pour
            // que les gabarits reçoivent exactement ce qu'ils recevaient.
            'members' => (string) $group->getMembersCount(),
            'badge' => $group->getBadge(),
        ];
    }

    /**
     * @param iterable<Group> $groups
     *
     * @return list<array<string, mixed>>
     */
    public function cards(iterable $groups): array
    {
        $cards = [];

        foreach ($groups as $group) {
            $cards[] = $this->card($group);
        }

        return $cards;
    }

    /**
     * @param iterable<GroupAlbum> $albums
     *
     * @return list<array<string, mixed>>
     */
    public function albums(iterable $albums): array
    {
        $cards = [];

        foreach ($albums as $album) {
            $cards[] = [
                'title' => $album->getTitle(),
                'location' => $album->getLocation(),
                'image' => $album->getImagePath() ?? self::FALLBACK_ALBUM_IMAGE,
                // « 05 photos » : la maquette complète à deux chiffres.
                'photos' => sprintf('%02d photos', $album->getPhotosCount()),
                'updated' => $this->updatedLabel($album),
            ];
        }

        return $cards;
    }

    /**
     * « Mis à jour le 28 Juill. 2026 ».
     */
    private function updatedLabel(GroupAlbum $album): string
    {
        $date = $album->getLastPhotoAt();

        if (null === $date) {
            return '';
        }

        // Le fuseau compte ici aussi : la colonne est un TIMESTAMPTZ rendu en
        // UTC, et une date de fin de journée changerait de jour a l'affichage.
        $local = $date->setTimezone(new \DateTimeZone(date_default_timezone_get()));

        return sprintf(
            'Mis à jour le %s %s %s',
            $local->format('d'),
            self::MOIS[(int) $local->format('n')],
            $local->format('Y'),
        );
    }
}
