<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Catalog\Entity\Category;
use App\Catalog\Entity\Media;
use App\Catalog\Entity\Service;
use App\Catalog\Entity\ServiceDetail;
use App\Catalog\Entity\ServicePackage;
use App\Catalog\Enum\BookingType;
use App\Catalog\Enum\PricingUnit;
use App\Catalog\Enum\ServiceStatus;
use App\Catalog\Presenter\ActivityPresenter;
use App\Provider\Entity\ProviderProfile;
use App\Provider\Enum\ProviderStatus;
use App\User\Entity\User;
use App\User\Enum\UserStatus;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Catalogue de la maquette, mis en base.
 *
 * LE CONTENU EST REPRIS MOT POUR MOT de App\Catalog\StaticCatalog. C'est la
 * condition posée au lot 2 : les huit écrans du parcours Activités sont calés
 * au pixel et validés ; un titre plus long, une ville différente ou une photo
 * d'un autre format les décalerait. Chaque chaîne ci-dessous doit donc rester
 * identique à celle de StaticCatalog tant que les deux cohabitent.
 *
 * Les notes et le nombre d'avis sont posés directement sur l'activité plutôt
 * que reconstitués à partir de vrais avis : afficher « 256 avis » demanderait
 * d'inventer 256 commentaires, ce qui n'apporterait rien et ralentirait le
 * chargement des données pour rien.
 */
class CatalogFixtures extends Fixture
{
    /**
     * Les huit activités du listing, dans l'ordre de la maquette.
     *
     * @var list<array<string, mixed>>
     */
    private const ACTIVITIES = [
        [
            'slug' => 'descente-en-canoe',
            'title' => 'Descente en Canoë',
            'place' => "Gorges de L'Ardèche",
            'rating' => '4.80',
            'reviews' => 256,
            'duration' => '2h-3h',
            'minutes' => 150,
            'price' => 25,
            'badge' => 'Bestseller',
            'image' => 'images/home/act-canoe.jpg',
            'lat' => '44.405000',
            'lng' => '4.395000',
            'category' => 'sports-aventures',
        ],
        [
            'slug' => 'location-vtt-electrique',
            'title' => 'Location VTT électrique',
            'place' => 'Massif du Vercors',
            'rating' => '4.80',
            'reviews' => 178,
            'duration' => 'Journée',
            'minutes' => 480,
            'price' => 45,
            'badge' => null,
            'image' => 'images/home/act-vtt.jpg',
            'lat' => '45.050000',
            'lng' => '5.400000',
            'category' => 'sports-aventures',
        ],
        [
            'slug' => 'visite-guidee-de-labyrinthe',
            'title' => 'Visite de labyrinthe',
            'place' => 'Labyrinthe en Provence',
            'rating' => '4.70',
            'reviews' => 134,
            'duration' => '1h30',
            'minutes' => 90,
            'price' => 12,
            'badge' => null,
            'image' => 'images/home/act-labyrinthe.jpg',
            'lat' => '43.830000',
            'lng' => '5.050000',
            'category' => 'cultures-decouvertes',
        ],
        [
            'slug' => 'visite-du-musee',
            'title' => 'Visite du Musée',
            'place' => "Muséum d'Histoire Naturelle",
            'rating' => '4.80',
            'reviews' => 312,
            'duration' => '2h',
            'minutes' => 120,
            'price' => 16,
            'badge' => null,
            'image' => 'images/home/act-musee.jpg',
            'lat' => '48.842000',
            'lng' => '2.356000',
            'category' => 'cultures-decouvertes',
        ],
        [
            'slug' => 'atelier-cuisine-provencale',
            'title' => 'Atelier cuisine provençale',
            'place' => "Provence-Alpes-Côte d'Azur",
            'rating' => '4.80',
            'reviews' => 64,
            'duration' => '2h30',
            'minutes' => 150,
            'price' => 25,
            'badge' => null,
            'image' => 'images/activities/cuisine.jpg',
            'lat' => '43.530000',
            'lng' => '5.450000',
            'category' => 'ateliers-creations',
        ],
        [
            'slug' => 'vol-en-montgolfiere',
            'title' => 'Vol en montgolfière',
            'place' => "Provence-Alpes-Côte d'Azur",
            'rating' => '5.00',
            'reviews' => 93,
            'duration' => '3h',
            'minutes' => 180,
            'price' => 180,
            'badge' => null,
            'image' => 'images/activities/montgolfiere.jpg',
            'lat' => '43.900000',
            'lng' => '6.000000',
            'category' => 'sports-aventures',
        ],
        [
            'slug' => 'seance-de-yoga-en-pleine-nature',
            'title' => 'Séance de yoga en pleine nature',
            'place' => 'Auvergne-Rhône-Alpes',
            'rating' => '4.90',
            'reviews' => 37,
            'duration' => '1h30',
            'minutes' => 90,
            'price' => 25,
            'badge' => null,
            'image' => 'images/activities/yoga.jpg',
            'lat' => '45.360000',
            'lng' => '4.800000',
            'category' => 'bien-etre',
        ],
        [
            'slug' => 'concert-live-soiree-musique',
            'title' => 'Concert live - Soirée musique',
            'place' => 'Lyon, Auvergne-Rhône-Alpes',
            'rating' => '4.50',
            'reviews' => 68,
            'duration' => '3h',
            'minutes' => 180,
            'price' => 30,
            'badge' => null,
            'image' => 'images/activities/soiree.jpg',
            'lat' => '45.760000',
            'lng' => '4.840000',
            'category' => 'soirees-evenements',
        ],
    ];

    /**
     * Contenu de la fiche détaillée, repris mot pour mot de la maquette.
     *
     * ⚠️ LA MAQUETTE N'EN FOURNIT QU'UNE SEULE, celle de la descente en canoë.
     * Le code statique l'affichait pour TOUTES les activités : ouvrir « Visite
     * du Musée » montrait « Descente en Canoë », titre compris. On reproduit ce
     * comportement à l'identique pour ne pas modifier le rendu, mais le contenu
     * réel des sept autres fiches reste à fournir par le client.
     *
     * @var array<string, mixed>
     */
    private const DETAIL = [
        'breadcrumb' => ['Accueil', 'Toutes les destinations', 'Paris, France', 'Sports & aventures'],
        'organizer' => 'Thomas Martin',
        'gallery' => [
            'images/activities/gallery-1.jpg',
            'images/home/act-canoe.jpg',
            'images/activities/canoe-riviere.jpg',
            'images/activities/gallery-2.jpg',
            'images/activities/gallery-3.jpg',
        ],
        'keyFacts' => [
            ['label' => 'Durée', 'value' => '2h-3h'],
            ['label' => 'Maximum de personnes', 'value' => '18 personnes'],
            ['label' => "Moyenne d'âge", 'value' => '12 ans +'],
            ['label' => "Type d'activités", 'value' => 'Sport & Aventure'],
            ['label' => 'Avis clients', 'value' => '4.8 (15 avis)', 'star' => true],
        ],
        'price' => 29,
        'presentationSubtitle' => "Descente intégrale des Gorges de l'Ardèche en canoë kayak",
        'presentationText' => "Vivez une aventure inoubliable au cœur d'un des plus beaux canyons d'Europe. Accompagné de votre moniteur diplômé, pagayez au fil de l'eau entre falaises vertigineuses et plages sauvages, à votre rythme, en famille ou entre amis.",
        'highlightsTitle' => 'Cette descente sportive vous permet de :',
        'highlights' => [
            "Passer sous l'arche naturelle du Pont d'Arc, emblème des Gorges",
            "Traverser la Réserve Naturelle des Gorges de l'Ardèche",
            'Admirer des paysages spectaculaires inaccessibles par la route',
            'Profiter de pauses baignade dans une eau limpide',
        ],
        'included' => [
            'La location des bateaux et du matériel de navigation',
            'Location du petit matériel (pagaies, gilets, bidons étanches)',
            "L'initiation de départ avec un moniteur diplômé",
            'Le retour des personnes & du matériel en fin de parcours',
        ],
        'excluded' => [
            'Les repas et les boissons',
            "L'équipement personnel (chaussures fermées, maillot)",
            "Le transport jusqu'à l'embarcadère de départ",
            "L'assurance annulation personnelle",
        ],
        'cannotParticipate' => [
            'Enfants de moins de 7 ans',
            'Femmes enceintes',
            'Personnes ne sachant pas nager',
            'Parcours non adapté aux débutants',
        ],
        'toBring' => [
            "De l'eau (1,5 L par personne minimum) et un pique-nique",
            'Chaussures fermées, maillot de bain, serviette, crème solaire et lunettes attachées',
        ],
        'mapImage' => 'images/activities/map.jpg',
        'meetingPoints' => [
            ['label' => 'Lieu de départ', 'value' => "A 8h30 depuis l'embarcadère"],
            ['label' => "Gorges de l'Ardèche", 'value' => "Vallon-Pont-d'Arc, France"],
            ['label' => 'Arrivée à', 'value' => 'Embarcadère parking Alain Bateau'],
        ],
        'guarantees' => [
            ['title' => 'Annulation : Flexible', 'text' => "Annulation gratuite jusqu'à 7 jours avant le départ."],
            ['title' => 'Garantie méteo', 'text' => 'Report ou remboursement si la météo est défavorable.'],
            ['title' => 'Paiement 100% sécurisé', 'text' => 'Réglez en toute confiance par carte ou Paypal.'],
            ['title' => "Une équipe d'experts", 'text' => 'À votre service 7j/7 pour préparer votre sortie.'],
        ],
        'reviewsScore' => '4,5',
        'reviewsOutOf' => 5,
        'reviewsTotal' => 8955,
        'modalTitle' => "Descente en Canoë de l'Ardèche de Gorges : 02 heures",
    ];

    /**
     * Catégories, avec les libellés exacts des pastilles de la maquette.
     *
     * @var array<string, string>
     */
    private const CATEGORIES = [
        'sports-aventures' => 'Sports & Aventures',
        // « Découverte » au singulier : c'est l'orthographe de la pastille sur
        // l'écran ville, seul endroit où ce libellé est affiché. La barre de
        // filtres du listing écrit « Cultures & Découverte » elle aussi, tandis
        // que le parcours Offres met un « s ». Incohérence de la maquette, à
        // signaler ; en attendant, on suit l'écran concerné.
        'cultures-decouvertes' => 'Cultures & Découverte',
        'ateliers-creations' => 'Ateliers & Créations',
        'bien-etre' => 'Bien-être',
        'soirees-evenements' => 'Soirées & Évènements',
        'en-famille' => 'En famille',
        'gastronomies' => 'Gastronomies',
    ];

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $provider = $this->createProvider($manager);
        $categories = $this->createCategories($manager);

        foreach (self::ACTIVITIES as $position => $data) {
            $service = new Service();
            $service
                ->setProvider($provider)
                ->setCategory($categories[$data['category']])
                ->setTitle((string) $data['title'])
                ->setSlug((string) $data['slug'])
                // La maquette ne fournit de texte de présentation que pour la
                // descente en canoë. Plutôt que d'en inventer sept, on reprend
                // le titre : la description n'est affichée sur aucun des écrans
                // câblés à ce stade.
                ->setDescription((string) $data['title'])
                ->setPlaceLabel((string) $data['place'])
                ->setDurationLabel((string) $data['duration'])
                ->setDurationMinutes((int) $data['minutes'])
                ->setBadge($data['badge'] ?? null)
                ->setRatingSummary((string) $data['rating'], (int) $data['reviews'])
                ->setLatitude((string) $data['lat'])
                ->setLongitude((string) $data['lng'])
                ->setCurrency('EUR')
                ->setBookingType(BookingType::Calendar)
                ->setStatus(ServiceStatus::Published);

            $service->addPackage(
                (new ServicePackage())
                    ->setName('Tarif unique')
                    ->setPrice(number_format((float) $data['price'], 2, '.', ''))
                    ->setCurrency('EUR')
                    ->setPricingUnit(PricingUnit::PerPerson),
            );

            // Vignette de la carte. Le type la distingue des photos de la
            // galerie, qui sont des medias eux aussi : sans cette distinction,
            // la carte afficherait la premiere photo du carrousel.
            $service->addMedia(
                (new Media())
                    ->setPath((string) $data['image'])
                    ->setType(ActivityPresenter::MEDIA_COVER)
                    ->setPosition(0),
            );

            foreach (self::DETAIL['gallery'] as $index => $path) {
                $service->addMedia(
                    (new Media())
                        ->setPath((string) $path)
                        ->setType(ActivityPresenter::MEDIA_GALLERY)
                        ->setPosition($index),
                );
            }

            $service->setDetail($this->buildDetail());

            // La position sert à retrouver l'ordre exact de la maquette : un
            // tri par titre ou par date de création le bousculerait.
            $service->setPosition($position);

            $manager->persist($service);
        }

        $manager->flush();
    }

    private function createProvider(ObjectManager $manager): ProviderProfile
    {
        $user = new User();
        $user->setEmail('annonceur@trouvemoi.test');
        $user->setFirstName('Camille');
        $user->setLastName('Diop');
        $user->setStatus(UserStatus::Active);
        // Ce compte porte un dossier prestataire vérifié : il doit donc aussi
        // porter le rôle, sinon il ne pourra pas entrer dans l'espace
        // professionnel. L'oubli datait d'avant le câblage, où le rôle ne
        // servait à rien.
        $user->setRoles(['ROLE_PROVIDER']);
        $user->setPassword($this->passwordHasher->hashPassword($user, 'Password123'));
        $manager->persist($user);

        $provider = new ProviderProfile();
        $provider->setUser($user);
        $provider->setDisplayName('Camille Aventures');
        $provider->setCompanyName('Aventures SARL');
        $provider->setStatus(ProviderStatus::Verified);
        $manager->persist($provider);

        return $provider;
    }

    /**
     * Construit le bloc editorial de la fiche.
     *
     * Le meme contenu pour les huit activites : la maquette n'en fournit
     * qu'un. Seuls le titre, le lieu, la note et le nombre d'avis different
     * d'une fiche a l'autre — ils viennent de l'activite elle-meme.
     */
    private function buildDetail(): ServiceDetail
    {
        $detail = new ServiceDetail();

        return $detail
            ->setBreadcrumb(self::DETAIL['breadcrumb'])
            ->setOrganizer(self::DETAIL['organizer'])
            ->setKeyFacts(self::DETAIL['keyFacts'])
            ->setPrice(self::DETAIL['price'])
            ->setPresentationSubtitle(self::DETAIL['presentationSubtitle'])
            ->setPresentationText(self::DETAIL['presentationText'])
            ->setHighlightsTitle(self::DETAIL['highlightsTitle'])
            ->setHighlights(self::DETAIL['highlights'])
            ->setIncluded(self::DETAIL['included'])
            ->setExcluded(self::DETAIL['excluded'])
            ->setCannotParticipate(self::DETAIL['cannotParticipate'])
            ->setToBring(self::DETAIL['toBring'])
            ->setMapImage(self::DETAIL['mapImage'])
            ->setMeetingPoints(self::DETAIL['meetingPoints'])
            ->setGuarantees(self::DETAIL['guarantees'])
            ->setReviewsSummary(
                self::DETAIL['reviewsScore'],
                self::DETAIL['reviewsOutOf'],
                self::DETAIL['reviewsTotal'],
            )
            ->setModalTitle(self::DETAIL['modalTitle']);
    }

    /**
     * @return array<string, Category>
     */
    private function createCategories(ObjectManager $manager): array
    {
        $categories = [];
        $position = 1;

        foreach (self::CATEGORIES as $slug => $name) {
            $category = new Category();
            $category->setName($name)->setSlug($slug)->setPosition($position++);
            $manager->persist($category);

            $categories[$slug] = $category;
        }

        return $categories;
    }
}
