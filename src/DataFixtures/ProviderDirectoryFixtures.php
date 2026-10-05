<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Catalog\Enum\ServiceStatus;
use App\Catalog\Service\ActivityDraftService;
use App\Provider\Entity\ProviderProfile;
use App\Provider\Enum\ProviderStatus;
use App\Provider\Service\ProviderSlugService;
use App\User\Entity\User;
use App\User\Enum\UserStatus;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Prestataires de démonstration pour l'annuaire « Trouver un professionnel »
 * (05/10). Leurs activités passent par ActivityDraftService, comme celles
 * créées dans l'assistant pro : fiche détaillée, photos et formules
 * complètes. Mot de passe de tous les comptes : Password123.
 */
final class ProviderDirectoryFixtures extends Fixture implements DependentFixtureInterface
{
    private const PROVIDERS = [
        ['Léa', 'Morel', 'Normandie Kayak', 'Normandie Kayak SAS', 'Rouen', 'natures-plein-air', 'Base nautique en bord de Seine : kayak, paddle et canoë encadrés par des moniteurs diplômés d’État, du débutant au confirmé.', [
            ['Canoë sur la Seine', 'natures-plein-air', 'Rouen', '76000', '49.4431', '1.0993', 'images/home/act-canoe.jpg', 25, 120, '4.8', 120],
            ['Paddle au coucher du soleil', 'sports-aventures', 'Rouen', '76000', '49.4400', '1.0900', 'images/events/ph-kayak2.jpg', 30, 90, '4.7', 41],
        ]],
        ['Hugo', 'Lefèvre', 'Speed Karting 76', 'Speed Karting SARL', 'Forges-les-Eaux', 'sports-aventures', 'Circuit de karting indoor et outdoor, sessions découverte, grands prix entre amis et séminaires.', [
            ['Karting', 'sports-aventures', 'Forges-les-Eaux', '76440', '49.6136', '1.5444', 'images/gifts/card-cyclist.jpg', 16, 30, '4.7', 63],
            ['Grand prix entre amis', 'soirees-evenements', 'Forges-les-Eaux', '76440', '49.6140', '1.5450', 'images/gifts/fg-diner.jpg', 39, 90, '4.6', 18],
        ]],
        ['Inès', 'Garnier', 'Escape Le Havre', 'Mystery Rooms EURL', 'Le Havre', 'cultures-decouvertes', 'Trois salles d’escape game immersives au cœur du Havre, de 2 à 6 joueurs, scénarios pour tous les âges.', [
            ['Escape Game', 'cultures-decouvertes', 'Le Havre', '76600', '49.4944', '0.1079', 'images/events/ev-catacombes.jpg', 12, 60, '4.6', 87],
            ['Escape game en famille', 'en-famille', 'Le Havre', '76600', '49.4950', '0.1080', 'images/gifts/dest-enfants.jpg', 18, 60, '4.8', 25],
        ]],
        ['Paul', 'Renaud', 'Zoo de Clères', 'Parc Zoologique de Clères', 'Clères', 'en-famille', 'Parc zoologique dans le domaine d’un château normand : girafes, kangourous, oiseaux rares et visites guidées.', [
            ['Parc Zoologique de Clères', 'en-famille', 'Clères', '76690', '49.6000', '1.1167', 'images/gifts/dest-femme.jpg', 45, 240, '4.7', 98],
            ['Soigneur d’un jour', 'en-famille', 'Clères', '76690', '49.6005', '1.1170', 'images/gifts/dest-homme.jpg', 89, 180, '4.9', 12],
        ]],
        ['Nora', 'Benali', 'Atelier des Saveurs', 'Les Saveurs Normandes', 'Dieppe', 'gastronomies', 'Cours de cuisine du terroir et ateliers pâtisserie en petit groupe, produits de saison et de la mer.', [
            ['Atelier cuisine de la mer', 'gastronomies', 'Dieppe', '76200', '49.9229', '1.0775', 'images/gifts/fg-cooking.jpg', 55, 180, '4.8', 44],
            ['Pâtisserie normande', 'ateliers-creations', 'Dieppe', '76200', '49.9220', '1.0780', 'images/gifts/fg-eclairs.jpg', 42, 150, '4.5', 19],
        ]],
    ];

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly ProviderSlugService $providerSlugger,
        private readonly ActivityDraftService $drafts,
    ) {
    }

    public function getDependencies(): array
    {
        return [CatalogFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        foreach (self::PROVIDERS as $i => [$first, $last, $name, $company, $city, $category, $bio, $activities]) {
            $user = (new User())
                ->setEmail(sprintf('pro%d@prestataire.trouvemoi.test', $i + 1))
                ->setFirstName($first)
                ->setLastName($last)
                ->setStatus(UserStatus::Active)
                ->setRoles(['ROLE_PROVIDER']);
            $user->setPassword($this->passwordHasher->hashPassword($user, 'Password123'));
            $manager->persist($user);

            $provider = (new ProviderProfile())
                ->setUser($user)
                ->setDisplayName($name)
                ->setCompanyName($company)
                ->setCity($city)
                ->setBio($bio)
                ->setStatus(ProviderStatus::Verified);
            $provider->setMainCategory($manager->getRepository(\App\Catalog\Entity\Category::class)->findOneBy(['slug' => $category]));
            $this->providerSlugger->assign($provider);
            $manager->persist($provider);
            $manager->flush();

            foreach ($activities as [$title, $cat, $place, $cp, $lat, $lng, $image, $price, $minutes, $rating, $reviews]) {
                $service = $this->drafts->persist([
                    'id' => null, 'title' => $title, 'category' => $cat, 'activity_type' => 'supervised', 'level' => 'all_levels',
                    'subtitle' => sprintf('%s avec %s, à %s.', $title, $name, $place), 'languages' => ['Français'],
                    'audience' => 'Tous publics', 'minimum_age' => '6', 'address' => $place, 'city' => $place, 'postal_code' => $cp,
                    'lat' => $lat, 'lng' => $lng, 'place_label' => $place.' ('.substr($cp, 0, 2).')', 'meeting_point' => 'Accueil du site, 15 minutes avant le début.',
                    'opening_period' => 'all_year', 'cover' => $image, 'gallery' => [$image],
                    'description' => $bio.' Une expérience encadrée par des professionnels passionnés, accessible à tous, au départ de '.$place.'.',
                    'programme' => '', 'duration' => (string) $minutes, 'capacity' => '12',
                    'highlights' => "Encadrement professionnel\nMatériel fourni", 'included' => "Équipement\nEncadrement", 'excluded' => 'Transport',
                    'to_bring' => 'Tenue confortable', 'cannot_participate' => '',
                    'packages' => [['name' => 'Tarif unique', 'price' => (string) $price, 'unit' => 'per_person', 'description' => '']],
                    'cancellation' => 'flexible', 'booking_type' => 'calendar',
                ], $provider);
                $service->setStatus(ServiceStatus::Published)->setRatingSummary($rating, $reviews);
            }
            $manager->flush();
        }
    }
}
