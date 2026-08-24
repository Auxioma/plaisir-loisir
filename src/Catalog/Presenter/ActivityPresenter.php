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
    /**
     * Photo affichée quand la fiche n'en a aucune.
     *
     * Une fiche sans photo faisait tomber les listings en erreur 500 :
     * `asset(null)` lève une exception. Le défaut ne se voyait pas en local,
     * où les données de démonstration ont toutes une image ; il est apparu en
     * production. Le repli est posé ICI, à la source du null, et non dans
     * chaque gabarit : le même champ alimente la carte, la grille du bas de
     * page et la liste des favoris.
     */
    private const FALLBACK_IMAGE = 'images/activities/canoe-riviere.jpg';

    /** Plan generique du bloc « rendez-vous ». */
    private const FALLBACK_MAP = 'images/activities/map.jpg';

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
     * @param list<string> $favoriteSlugs activités déjà en favori chez le visiteur
     *
     * @return array<string, mixed>
     */
    public function card(Service $service, bool $withCategory = false, array $favoriteSlugs = []): array
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
            'image' => $this->coverImage($service) ?? self::FALLBACK_IMAGE,
            'lat' => null !== $service->getLatitude() ? (float) $service->getLatitude() : null,
            'lng' => null !== $service->getLongitude() ? (float) $service->getLongitude() : null,
            'favorite' => \in_array($service->getSlug(), $favoriteSlugs, true),
        ];

        if ($withCategory) {
            $card['category'] = $service->getCategory()?->getName();
        }

        return $card;
    }

    /**
     * @param iterable<Service> $services
     * @param list<string>      $favoriteSlugs
     *
     * @return list<array<string, mixed>>
     */
    public function cards(iterable $services, bool $withCategory = false, array $favoriteSlugs = []): array
    {
        $cards = [];

        foreach ($services as $service) {
            $cards[] = $this->card($service, $withCategory, $favoriteSlugs);
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
     * Fiche détaillée de la page publique.
     *
     * NE RENVOIE PLUS NULL, ET C'EST LE POINT IMPORTANT.
     *
     * Auparavant, une activité sans contenu éditorial renvoyait null et le
     * contrôleur rendait une erreur 404. Le raisonnement se tenait tant que
     * les données venaient des fixtures, où tout est rempli. Confronté aux
     * vraies données, il donnait ceci : le 24/08, les QUATRE activités du
     * catalogue en production menaient à une page d'erreur. Le visiteur voyait
     * une carte, cliquait, et tombait sur une erreur.
     *
     * Une activité publiée doit avoir une page. Quand la fiche éditoriale
     * manque, on la construit à partir de ce que l'activité sait d'elle-même —
     * son titre, son lieu, sa durée, ses photos, le prix de ses formules, son
     * prestataire. Rien n'est inventé : ce sont des champs que le back-office
     * propose déjà à la saisie. Les blocs qui restent vides sont masqués par le
     * gabarit plutôt que rendus à moitié.
     *
     * @return array<string, mixed>
     */
    public function detail(Service $service): array
    {
        $detail = $service->getDetail();

        return [
            'breadcrumb' => $detail?->getBreadcrumb() ?? [],
            'title' => $service->getTitle(),
            'rating' => $this->rating($service),
            'reviewsCount' => $service->getReviewsCount(),
            // À défaut de texte saisi, le nom du prestataire : c'est bien lui
            // qui organise.
            'organizer' => $detail?->getOrganizer() ?? $service->getProvider()?->getDisplayName(),
            'gallery' => $this->gallery($service),
            'place' => $service->getPlaceLabel(),
            'keyFacts' => $detail?->getKeyFacts() ?: $this->keyFactsFromService($service),
            'price' => $detail?->getPrice() ?? $this->price($service),
            'presentation' => [
                'subtitle' => $detail?->getPresentationSubtitle() ?? $service->getSubtitle() ?? $service->getShortDescription(),
                'text' => $detail?->getPresentationText() ?? $service->getDescription(),
                'bulletsTitle' => $detail?->getHighlightsTitle(),
                'bullets' => $detail?->getHighlights() ?? [],
            ],
            'included' => $detail?->getIncluded() ?? [],
            'excluded' => $detail?->getExcluded() ?? [],
            'cannotParticipate' => $detail?->getCannotParticipate() ?? [],
            'toBring' => $detail?->getToBring() ?? [],
            'logistics' => [
                // CINQUIEME occurrence du meme defaut : asset(null) fait
                // tomber la page. Ici c'est le plan du point de depart, absent
                // tant que personne ne l'a televerse. Le repli n'invente rien :
                // map.jpg est une image DECORATIVE, la meme pour toutes les
                // activites dans les fixtures comme dans le catalogue statique.
                'map' => $detail?->getMapImage() ?? self::FALLBACK_MAP,
                'meeting' => $detail?->getMeetingPoints() ?: $this->meetingFromService($service),
                'guarantees' => $detail?->getGuarantees() ?? [],
            ],
            'reviewsSummary' => [
                'score' => $detail?->getReviewsScore() ?? $this->rating($service),
                'outOf' => $detail?->getReviewsOutOf() ?? 5,
                'total' => $detail?->getReviewsTotal() ?? $service->getReviewsCount(),
            ],
            'modalTitle' => $detail?->getModalTitle() ?? $service->getTitle(),
        ];
    }

    /**
     * Le bandeau « en bref », reconstitué depuis l'activité.
     *
     * Les intitulés sont ceux de la maquette. Seules les informations
     * réellement saisies apparaissent : mieux vaut trois lignes justes que
     * cinq dont deux vides.
     *
     * @return list<array{label: string, value: string}>
     */
    private function keyFactsFromService(Service $service): array
    {
        $facts = [];

        if (null !== $service->getDurationLabel()) {
            $facts[] = ['label' => 'Durée', 'value' => $service->getDurationLabel()];
        }

        if (null !== $service->getCapacity()) {
            $facts[] = ['label' => 'Maximum de personnes', 'value' => sprintf('%d personnes', $service->getCapacity())];
        }

        if (null !== $service->getMinimumAge()) {
            $facts[] = ['label' => "Moyenne d'âge", 'value' => sprintf('%d ans +', $service->getMinimumAge())];
        }

        if (null !== $service->getCategory()) {
            $facts[] = ['label' => "Type d'activités", 'value' => $service->getCategory()->getName()];
        }

        return $facts;
    }

    /**
     * Le bloc « rendez-vous », reconstitué depuis l'activité.
     *
     * @return list<array{label: string, value: string}>
     */
    private function meetingFromService(Service $service): array
    {
        $lignes = [];

        if (null !== $service->getMeetingPoint()) {
            $lignes[] = ['label' => 'Point de départ', 'value' => $service->getMeetingPoint()];
        }

        if (null !== $service->getAddress()) {
            $lignes[] = ['label' => 'Adresse', 'value' => $service->getAddress()];
        }

        return $lignes;
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
