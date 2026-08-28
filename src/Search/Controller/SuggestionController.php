<?php

declare(strict_types=1);

namespace App\Search\Controller;

use App\Catalog\Repository\DestinationRepository;
use App\Catalog\Repository\ServiceRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Propositions affichées pendant la frappe dans les champs de recherche.
 *
 * POURQUOI CETTE ROUTE EXISTE
 * Les douze champs de recherche du site étaient muets : il fallait connaître
 * l'orthographe exacte d'une ville pour la trouver. Taper « pa » doit proposer
 * Paris — demande du CTO du 25/08.
 *
 * CE QU'ELLE RENVOIE, ET CE QU'ELLE NE RENVOIE PAS
 * Uniquement du contenu PUBLIC et PUBLIÉ : les noms des destinations, les
 * lieux et les titres des activités en ligne. Rien qui ne soit déjà lisible
 * sur le catalogue. Un point d'entrée de suggestion est interrogé par tout le
 * monde, sans compte : il ne doit jamais servir à deviner ce qui n'est pas
 * publié.
 *
 * GET, et c'est correct : la requête ne modifie rien, elle se met en cache et
 * ne transporte qu'un fragment de mot déjà tapé à l'écran.
 */
final class SuggestionController extends AbstractController
{
    /**
     * En deçà, la liste serait trop longue pour être utile : « p » remonterait
     * la moitié du catalogue.
     */
    private const MINIMUM = 2;

    /** Autant que la maquette peut en montrer sans faire défiler. */
    private const MAXIMUM = 8;

    public function __construct(
        private readonly ServiceRepository $services,
        private readonly DestinationRepository $destinations,
    ) {
    }

    #[Route(
        path: ['fr' => '/suggestions', 'en' => '/en/suggestions'],
        name: 'app_suggestions',
        methods: ['GET'],
    )]
    public function __invoke(Request $request): JsonResponse
    {
        $saisie = trim((string) $request->query->get('q', ''));
        $type = 'lieu' === $request->query->get('type') ? 'lieu' : 'activite';

        if (mb_strlen($saisie) < self::MINIMUM) {
            return $this->json(['items' => []]);
        }

        $items = 'lieu' === $type
            ? $this->lieux($saisie)
            : $this->activites($saisie);

        // Une suggestion vieille de quelques minutes reste bonne : le cache
        // évite de rejouer la requête à chaque frappe de chaque visiteur.
        return $this->json(['items' => $items])->setPublic()->setMaxAge(300);
    }

    /**
     * Destinations d'abord, lieux des activités ensuite.
     *
     * Une destination est une page du site : la proposer en premier mène
     * quelque part. Les lieux des activités complètent la liste pour les
     * villes qui n'ont pas encore leur page.
     *
     * @return list<array{label: string, url: string|null}>
     */
    private function lieux(string $saisie): array
    {
        $items = [];
        $vus = [];

        foreach ($this->destinations->searchByName($saisie, self::MAXIMUM) as $destination) {
            $items[] = [
                'label' => $destination->getName(),
                'url' => $this->generateUrl('app_destination_city', ['ville' => $destination->getSlug()]),
            ];
            $vus[mb_strtolower($destination->getName())] = true;
        }

        foreach ($this->services->suggestPlaces($saisie, self::MAXIMUM) as $lieu) {
            if (\count($items) >= self::MAXIMUM) {
                break;
            }

            // « Paris » proposé par une destination ET par le lieu d'une
            // activité ne doit apparaître qu'une fois.
            if (isset($vus[mb_strtolower($lieu)])) {
                continue;
            }

            $items[] = ['label' => $lieu, 'url' => null];
            $vus[mb_strtolower($lieu)] = true;
        }

        return $items;
    }

    /**
     * @return list<array{label: string, url: string|null}>
     */
    private function activites(string $saisie): array
    {
        $items = [];

        foreach ($this->services->suggestTitles($saisie, self::MAXIMUM) as $activite) {
            $items[] = [
                'label' => $activite['label'],
                'url' => $this->generateUrl('app_activity_show', ['slug' => $activite['slug']]),
            ];
        }

        return $items;
    }
}
