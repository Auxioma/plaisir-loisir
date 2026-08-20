<?php

declare(strict_types=1);

namespace App\Catalog\Presenter;

use App\Catalog\Entity\Service;

/**
 * Traduit une entité Service en la forme de tableau que les gabarits attendent.
 *
 * POURQUOI CETTE CLASSE
 * Les huit écrans du parcours Activités sont calés au pixel et validés. Leur
 * faire consommer directement des entités obligerait à réécrire chaque
 * `a.place`, `a.rating`, `a.duration`… c'est-à-dire à rouvrir des gabarits que
 * personne ne veut retoucher. Le présentateur absorbe la différence : les
 * gabarits ne changent pas d'un caractère, et la base remplace les classes
 * `Static*` derrière eux.
 *
 * La forme produite est exactement celle de StaticCatalog::activities() :
 * slug, place, title, rating, reviews, duration, price, badge, image, lat, lng.
 */
final class ActivityPresenter
{
    /** Vignette de la carte. */
    public const MEDIA_COVER = 'cover';

    /** Photos du carrousel de la fiche détaillée. */
    public const MEDIA_GALLERY = 'gallery';

    /**
     * Carte d'activité du listing et des grilles.
     *
     * `withCategory` n'est PAS un détail : sur /activites la maquette n'affiche
     * aucune pastille de catégorie, alors que le listing d'une ville en met
     * une. Ajouter la clé partout ferait apparaître une pastille là où la
     * maquette n'en veut pas.
     *
     * @return array<string, mixed>
     */
    public function card(Service $service, bool $withCategory = false): array
    {
        $card = [
            'slug' => $service->getSlug(),
            'place' => $service->getPlaceLabel(),
            'title' => $service->getTitle(),
            'rating' => $this->rating($service),
            'reviews' => $service->getReviewsCount(),
            'duration' => $service->getDurationLabel(),
            'price' => $this->price($service),
            'badge' => $service->getBadge(),
            'image' => $this->coverImage($service),
            'lat' => null !== $service->getLatitude() ? (float) $service->getLatitude() : null,
            'lng' => null !== $service->getLongitude() ? (float) $service->getLongitude() : null,
        ];

        if ($withCategory) {
            $card['category'] = $service->getCategory()?->getName();
        }

        return $card;
    }

    /**
     * @param iterable<Service> $services
     *
     * @return list<array<string, mixed>>
     */
    public function cards(iterable $services, bool $withCategory = false): array
    {
        $cards = [];

        foreach ($services as $service) {
            $cards[] = $this->card($service, $withCategory);
        }

        return $cards;
    }

    /**
     * Les mêmes cartes, indexées par slug.
     *
     * StaticCatalog::activities() renvoyait un tableau associatif ; certains
     * appels s'appuient dessus pour composer des rangées précises.
     *
     * @param iterable<Service> $services
     *
     * @return array<string, array<string, mixed>>
     */
    public function cardsBySlug(iterable $services, bool $withCategory = false): array
    {
        $cards = [];

        foreach ($services as $service) {
            $cards[$service->getSlug()] = $this->card($service, $withCategory);
        }

        return $cards;
    }

    /**
     * Fiche détaillée, dans la forme que StaticCatalog::detail() produisait.
     *
     * Renvoie null si l'activité n'a pas de contenu éditorial : c'est à
     * l'appelant de décider quoi faire, plutôt que de laisser l'écran se
     * rendre à moitié vide.
     *
     * @return array<string, mixed>|null
     */
    public function detail(Service $service): ?array
    {
        $detail = $service->getDetail();

        if (null === $detail) {
            return null;
        }

        return [
            'breadcrumb' => $detail->getBreadcrumb(),
            'title' => $service->getTitle(),
            'rating' => $this->rating($service),
            'reviewsCount' => $service->getReviewsCount(),
            'organizer' => $detail->getOrganizer(),
            'gallery' => $this->gallery($service),
            'place' => $service->getPlaceLabel(),
            'keyFacts' => $detail->getKeyFacts(),
            'price' => $detail->getPrice(),
            'presentation' => [
                'subtitle' => $detail->getPresentationSubtitle(),
                'text' => $detail->getPresentationText(),
                'bulletsTitle' => $detail->getHighlightsTitle(),
                'bullets' => $detail->getHighlights(),
            ],
            'included' => $detail->getIncluded(),
            'excluded' => $detail->getExcluded(),
            'cannotParticipate' => $detail->getCannotParticipate(),
            'toBring' => $detail->getToBring(),
            'logistics' => [
                'map' => $detail->getMapImage(),
                'meeting' => $detail->getMeetingPoints(),
                'guarantees' => $detail->getGuarantees(),
            ],
            'reviewsSummary' => [
                'score' => $detail->getReviewsScore(),
                'outOf' => $detail->getReviewsOutOf(),
                'total' => $detail->getReviewsTotal(),
            ],
            'modalTitle' => $detail->getModalTitle(),
        ];
    }

    /**
     * Note affichée : « 4.8 », avec une seule décimale.
     *
     * La colonne est un décimal à deux décimales, que Doctrine rend sous forme
     * de chaîne (« 4.80 ») : affichée telle quelle, elle donnerait « 4.80 » là
     * où la maquette écrit « 4.8 ».
     */
    private function rating(Service $service): ?string
    {
        $average = $service->getRatingAverage();

        return null !== $average ? number_format((float) $average, 1, '.', '') : null;
    }

    /**
     * Prix affiché : le plus bas des formules proposées.
     *
     * La maquette montre un prix « à partir de » ; une activité peut avoir
     * plusieurs formules. Rendu en entier quand le montant est rond, comme sur
     * la maquette qui écrit « 25 € » et jamais « 25,00 € ».
     */
    private function price(Service $service): int|float|null
    {
        $lowest = null;

        foreach ($service->getPackages() as $package) {
            $price = (float) $package->getPrice();

            if (null === $lowest || $price < $lowest) {
                $lowest = $price;
            }
        }

        if (null === $lowest) {
            return null;
        }

        return $lowest === floor($lowest) ? (int) $lowest : $lowest;
    }

    /**
     * Image de couverture : le premier média de type « cover ».
     *
     * Le filtre sur le type est indispensable depuis que les photos de la
     * galerie sont elles aussi des médias : sans lui, la vignette de la carte
     * deviendrait la première photo de la galerie, qui n'est pas la même image.
     */
    private function coverImage(Service $service): ?string
    {
        return $this->firstPathOfType($service, self::MEDIA_COVER);
    }

    /**
     * Les photos de la galerie, dans l'ordre.
     *
     * @return list<string>
     */
    private function gallery(Service $service): array
    {
        $items = [];

        foreach ($service->getMedia() as $media) {
            if (self::MEDIA_GALLERY === $media->getType()) {
                $items[] = ['position' => $media->getPosition(), 'path' => $media->getPath()];
            }
        }

        usort($items, static fn (array $a, array $b): int => $a['position'] <=> $b['position']);

        return array_map(static fn (array $item): string => $item['path'], $items);
    }

    private function firstPathOfType(Service $service, string $type): ?string
    {
        $path = null;
        $bestPosition = null;

        foreach ($service->getMedia() as $media) {
            if ($type !== $media->getType()) {
                continue;
            }

            if (null === $bestPosition || $media->getPosition() < $bestPosition) {
                $bestPosition = $media->getPosition();
                $path = $media->getPath();
            }
        }

        return $path;
    }
}
