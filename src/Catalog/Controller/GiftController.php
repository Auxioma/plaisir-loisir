<?php

declare(strict_types=1);

namespace App\Catalog\Controller;

use App\Catalog\Entity\GiftCard;
use App\Catalog\Enum\ActivitySort;
use App\Catalog\Enum\ServiceStatus;
use App\Catalog\Presenter\ActivityPresenter;
use App\Catalog\Repository\CategoryRepository;
use App\Catalog\Repository\GiftCardRepository;
use App\Catalog\Repository\ServiceRepository;
use App\Catalog\Service\GiftCardService;
use App\User\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * « Bons cadeaux » — maquette docs/maquettes/bons_cadeaux.jpeg (04/10).
 *
 * /cadeaux : les activités à offrir (filtres montant / catégorie / type),
 * /cadeaux/offrir : la saisie (bénéficiaire, envoi, message, acheteur),
 * puis le paiement (GiftCardService) et la confirmation avec le code.
 * Les bons sont réels (GiftCard) et suivis dans le back-office.
 */
final class GiftController extends AbstractController
{
    /** Tranches de la maquette : clé d'URL → [min, max]. */
    public const AMOUNTS = ['0-25' => [0, 25], '25-50' => [25, 50], '50-100' => [50, 100], '100-200' => [100, 200], '200+' => [200, null]];

    private const AMOUNT_LABELS = ['0-25' => 'Moins de 25 €', '25-50' => '25 € à 50 €', '50-100' => '50 € à 100 €', '100-200' => '100 € à 200 €', '200+' => 'Plus de 200 €'];

    private const PER_PAGE = 5;

    /** Visuels des tuiles « Catégories populaires » (slug → image, icône). */
    private const CATEGORY_LOOKS = [
        'sports-aventures' => ['images/gifts/tile-sports.jpg', 'cat_hiking', 'green'],
        'bien-etre' => ['images/gifts/tile-bienetre.jpg', 'cat_wellness', 'violet'],
        'cultures-decouvertes' => ['images/gifts/tile-cultures.jpg', 'cat_culture', 'orange'],
        'en-famille' => ['images/gifts/dest-enfants.jpg', 'cat_family', 'red'],
        'gastronomies' => ['images/gifts/tile-gastronomies.jpg', 'utensils', 'yellow'],
        'ateliers-creations' => ['images/gifts/tile-ateliers.jpg', 'cat_crafts', 'blue'],
        'natures-plein-air' => ['images/gifts/card-kayak.jpg', 'tree', 'green'],
        'soirees-evenements' => ['images/gifts/fg-diner.jpg', 'party', 'violet'],
    ];

    public function __construct(
        private readonly ServiceRepository $services,
        private readonly CategoryRepository $categories,
        private readonly ActivityPresenter $presenter,
        private readonly GiftCardService $giftCards,
    ) {
    }

    #[Route(path: ['fr' => '/cadeaux', 'en' => '/en/gift-cards'], name: 'app_gifts')]
    public function index(Request $request): Response
    {
        $q = $request->query;
        $amounts = array_values(array_intersect($q->all('montant'), array_keys(self::AMOUNTS)));
        $slugs = array_values(array_filter(array_map('strval', $q->all('categorie'))));
        $type = \in_array($q->get('type'), ['classique', 'libre'], true) ? (string) $q->get('type') : '';
        $delivery = array_values(array_intersect($q->all('envoi'), array_keys(GiftCardService::DELIVERIES)));
        $page = max(1, $q->getInt('page', 1));

        // Plusieurs tranches cochées : on prend l'enveloppe (de la plus basse à la plus haute).
        $min = [] !== $amounts ? min(array_map(static fn (string $k): int => self::AMOUNTS[$k][0], $amounts)) : null;
        $max = [] !== $amounts && !\in_array('200+', $amounts, true) ? max(array_map(static fn (string $k): int => (int) self::AMOUNTS[$k][1], $amounts)) : null;

        $result = $this->services->paginateForListing(1, self::PER_PAGE * $page, ActivitySort::Popular, categorySlugs: $slugs, priceMin: $min ?: null, priceMax: $max);

        $tiles = [];
        foreach ($this->categories->findBy(['parent' => null], ['position' => 'ASC']) as $category) {
            $look = self::CATEGORY_LOOKS[$category->getSlug()] ?? ['images/gifts/hero-coffret.jpg', 'gift', 'blue'];
            $tiles[] = [
                'slug' => $category->getSlug(),
                'name' => $category->getName(),
                'count' => $this->services->paginateForListing(1, 1, categorySlugs: [$category->getSlug()])['total'],
                'image' => $look[0], 'icon' => $look[1], 'tone' => $look[2],
            ];
        }

        $params = array_filter(['montant' => $amounts ?: null, 'categorie' => $slugs ?: null, 'type' => $type ?: null, 'envoi' => $delivery ?: null], static fn (mixed $v): bool => null !== $v);

        return $this->render('gift/index.html.twig', [
            'cards' => 'libre' === $type ? [] : $this->presenter->cards($result['items']),
            'total' => 'libre' === $type ? 0 : $result['total'],
            'has_more' => 'libre' !== $type && $result['total'] > self::PER_PAGE * $page,
            'page' => $page,
            'tiles' => $tiles,
            'filters' => ['montant' => $amounts, 'categorie' => $slugs, 'type' => $type, 'envoi' => $delivery],
            'params' => $params,
            'amount_labels' => self::AMOUNT_LABELS,
            'deliveries' => GiftCardService::DELIVERIES,
            'current_category' => 1 === \count($slugs) ? $this->categories->findOneBy(['slug' => $slugs[0]]) : null,
        ]);
    }

    /** Ancien écran « catégorie » de la maquette précédente : c'est désormais un filtre de /cadeaux. */
    #[Route(path: ['fr' => '/cadeaux/ateliers-creations', 'en' => '/en/gift-cards/workshops-and-crafts'], name: 'app_gifts_category')]
    public function category(): Response
    {
        return $this->redirectToRoute('app_gifts', ['categorie' => ['ateliers-creations']], Response::HTTP_MOVED_PERMANENTLY);
    }

    #[Route(path: ['fr' => '/cadeaux/offrir', 'en' => '/en/gift-cards/buy'], name: 'app_gifts_offer', methods: ['GET', 'POST'])]
    public function offer(Request $request): Response
    {
        $slug = (string) ($request->request->get('activite') ?? $request->query->get('activite', ''));
        $service = '' !== $slug ? $this->services->findPublishedBySlug($slug) : null;
        if ('' !== $slug && (null === $service || ServiceStatus::Published !== $service->getStatus())) {
            throw $this->createNotFoundException('Activité introuvable.');
        }
        if (null !== $service && null === $this->giftCards->amountFor($service, 1)) {
            $this->addFlash('info', 'Cette activité n’a pas encore de tarif : offrez un bon cadeau libre.');
            $service = null;
        }

        $user = $this->getUser();
        $preferred = (string) $request->query->get('envoi', GiftCard::DELIVERY_EMAIL);
        $data = [
            'people' => '1', 'amount' => (string) $request->query->getInt('montant', 50),
            'recipient_name' => '', 'recipient_email' => '', 'postal_address' => '', 'message' => '',
            'delivery' => \array_key_exists($preferred, GiftCardService::DELIVERIES) ? $preferred : GiftCard::DELIVERY_EMAIL,
            'buyer_name' => $user instanceof User ? trim($user->getFirstName().' '.$user->getLastName()) : '',
            'buyer_email' => $user instanceof User ? $user->getEmail() : '',
            'buyer_phone' => '', 'accept_terms' => '',
        ];
        $errors = [];

        if ($request->isMethod('POST')) {
            foreach (array_keys($data) as $key) {
                $data[$key] = trim((string) $request->request->get($key, ''));
            }
            if (!$this->isCsrfTokenValid('gift_offer', (string) $request->request->get('_token'))) {
                $errors['_token'] = 'Votre session a expiré, merci de renvoyer le formulaire.';
            } else {
                $errors = $this->giftCards->validate($data, $service);
            }

            if ([] === $errors) {
                $card = $this->giftCards->create($data, $service, $user instanceof User ? $user : null);

                try {
                    return $this->redirect($this->giftCards->checkout($card));
                } catch (\Throwable) {
                    $errors['_token'] = 'Le paiement n’a pas pu démarrer. Merci de réessayer dans un instant.';
                }
            }
        }

        return $this->render('gift/offrir.html.twig', [
            'activity' => null !== $service ? $this->presenter->card($service, true) : null,
            'unit' => null !== $service ? (float) $this->giftCards->amountFor($service, 1) : null,
            'total' => null !== $service ? (float) $this->giftCards->amountFor($service, max(1, min(GiftCardService::MAX_PEOPLE, (int) $data['people']))) : (float) (int) $data['amount'],
            'data' => $data,
            'errors' => $errors,
            'deliveries' => GiftCardService::DELIVERIES,
            'limits' => ['min' => GiftCardService::MIN_AMOUNT, 'max' => GiftCardService::MAX_AMOUNT, 'people' => GiftCardService::MAX_PEOPLE],
        ], new Response(null, [] !== $errors ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    /** Ancienne étape « paiement » : le paiement part désormais du formulaire. */
    #[Route(path: ['fr' => '/cadeaux/offrir/paiement', 'en' => '/en/gift-cards/buy/payment'], name: 'app_gifts_offer_payment', methods: ['GET'])]
    public function payment(): Response
    {
        return $this->redirectToRoute('app_gifts_offer');
    }

    /** Le lien (ULID, non devinable) est celui envoyé par e-mail à l'acheteur. */
    #[Route(path: ['fr' => '/cadeaux/bon/{id}', 'en' => '/en/gift-cards/card/{id}'], name: 'app_gifts_confirmation', requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'])]
    public function confirmation(string $id, GiftCardRepository $cards): Response
    {
        $card = $cards->find($id);
        if (null === $card) {
            throw $this->createNotFoundException();
        }

        return $this->render('gift/confirmation.html.twig', [
            'card' => $card,
            'activity' => null !== $card->getService() ? $this->presenter->card($card->getService()) : null,
        ]);
    }
}
