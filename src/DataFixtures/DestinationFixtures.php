<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Catalog\Entity\Destination;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Les seize destinations de la maquette, mises en base.
 *
 * Contenu repris MOT POUR MOT de App\Catalog\StaticDestinations::popular(),
 * pour la même raison que le catalogue d'activités : les sept écrans du
 * parcours Destinations sont calés au pixel et validés.
 *
 * Deux cartes s'appellent « Suisse » et ne diffèrent que par leur photo. Le
 * nom affiché reste le même, mais le slug doit être unique en base : d'où
 * « suisse-1 » et « suisse-2 ».
 */
class DestinationFixtures extends Fixture
{
    /**
     * Valeurs de remplissage de la maquette : à partir de la sixième carte,
     * elle répète la même accroche, la même note et le même volume.
     */
    private const FILLER = [
        'tagline' => 'Couleurs, saveurs et traditions',
        'rating' => '4.70',
        'reviews' => 189,
        'count' => 30,
        'price' => 22,
        'badge' => null,
    ];

    /**
     * @var list<array<string, mixed>>
     */
    private const DESTINATIONS = [
        [
            'name' => 'Paris, France', 'slug' => 'paris-france', 'country' => 'FR',
            'tagline' => 'Ville lumière et capitale de la culture',
            'rating' => '4.80', 'reviews' => 256, 'count' => 32, 'price' => 25,
            'badge' => 'Populaire', 'image' => 'images/home/dest-paris.webp',
        ],
        [
            'name' => 'Annecy, France', 'slug' => 'annecy-france', 'country' => 'FR',
            'tagline' => 'Entre lac et Montagnes',
            'rating' => '4.30', 'reviews' => 178, 'count' => 24, 'price' => 20,
            'badge' => 'Bestseller', 'image' => 'images/home/dest-annecy.png',
        ],
        [
            'name' => 'Cinque, Italie', 'slug' => 'cinque-italie', 'country' => 'IT',
            'tagline' => 'Vue à couper le souffle',
            'rating' => '4.70', 'reviews' => 134, 'count' => 18, 'price' => 30,
            'badge' => 'Tendance', 'image' => 'images/home/dest-cinqueterre.jpg',
        ],
        [
            'name' => 'Bali, Indonésie', 'slug' => 'bali-indonesie', 'country' => 'ID',
            'tagline' => 'Détente, nature et spiritualité',
            'rating' => '4.90', 'reviews' => 312, 'count' => 27, 'price' => 35,
            'badge' => null, 'image' => 'images/home/dest-bali.png',
        ],
        [
            'name' => 'New York, USA', 'slug' => 'new-york-usa', 'country' => 'US',
            'tagline' => 'La ville qui ne dort jamais',
            'rating' => '4.80', 'reviews' => 412, 'count' => 31, 'price' => 40,
            'badge' => null, 'image' => 'images/destinations/dest-newyork.jpg',
        ],
        ['name' => 'Marrakech, Maroc', 'slug' => 'marrakech-maroc', 'country' => 'MA', 'image' => 'images/destinations/dest-marrakech.jpg', 'tagline' => 'Souks, riads et palmeraie', 'rating' => '4.60', 'reviews' => 203, 'count' => 22, 'price' => 28],
        ['name' => 'Côte Amalfitaine, Italie', 'slug' => 'cote-amalfitaine-italie', 'country' => 'IT', 'image' => 'images/destinations/dest-amalfi.jpg', 'tagline' => 'Villages colorés et mer turquoise', 'rating' => '4.80', 'reviews' => 241, 'count' => 19, 'price' => 45],
        ['name' => 'Portugal', 'slug' => 'portugal', 'country' => 'PT', 'image' => 'images/destinations/dest-portugal.jpg', 'tagline' => 'Littoral, fado et pastéis de nata', 'rating' => '4.50', 'reviews' => 167, 'count' => 21, 'price' => 30],
        ['name' => 'Thaïlande', 'slug' => 'thailande', 'country' => 'TH', 'image' => 'images/destinations/dest-thailande.jpg', 'tagline' => 'Temples, plages et cuisine de rue', 'rating' => '4.70', 'reviews' => 288, 'count' => 24, 'price' => 38],
        ['name' => 'Allemagne', 'slug' => 'allemagne', 'country' => 'DE', 'image' => 'images/destinations/dest-allemagne.jpg', 'tagline' => 'Châteaux, forêts et marchés de Noël', 'rating' => '4.30', 'reviews' => 98, 'count' => 15, 'price' => 32],
        ['name' => 'République tchèque', 'slug' => 'republique-tcheque', 'country' => 'CZ', 'image' => 'images/destinations/dest-tcheque.jpg', 'tagline' => 'Prague et ses ponts de légende', 'rating' => '4.40', 'reviews' => 121, 'count' => 14, 'price' => 26],
        ['name' => 'Alpes suisses', 'slug' => 'suisse-1', 'country' => 'CH', 'image' => 'images/destinations/dest-suisse-1.jpg', 'tagline' => 'Sommets, glaciers et chalets', 'rating' => '4.90', 'reviews' => 176, 'count' => 20, 'price' => 60],
        ['name' => 'Lac Léman, Suisse', 'slug' => 'suisse-2', 'country' => 'CH', 'image' => 'images/destinations/dest-suisse-2.jpg', 'tagline' => 'Lac, vignobles en terrasse et montagne', 'rating' => '4.60', 'reviews' => 88, 'count' => 12, 'price' => 55],
        ['name' => 'Égypte', 'slug' => 'egypte', 'country' => 'EG', 'image' => 'images/destinations/dest-egypte.jpg', 'tagline' => 'Pyramides et croisières sur le Nil', 'rating' => '4.50', 'reviews' => 154, 'count' => 16, 'price' => 48],
        ['name' => 'Canada', 'slug' => 'canada', 'country' => 'CA', 'image' => 'images/destinations/dest-canada.jpg', 'tagline' => 'Grands espaces, lacs et forêts', 'rating' => '4.70', 'reviews' => 132, 'count' => 18, 'price' => 65],
        ['name' => 'Japon', 'slug' => 'japon', 'country' => 'JP', 'image' => 'images/destinations/dest-japon.jpg', 'tagline' => 'Tradition, jardins et métropoles', 'rating' => '4.80', 'reviews' => 219, 'count' => 23, 'price' => 70],
    ];

    public function load(ObjectManager $manager): void
    {
        foreach (self::DESTINATIONS as $position => $data) {
            $values = array_merge(self::FILLER, $data);

            $destination = new Destination();
            $destination
                ->setName((string) $values['name'])
                ->setSlug((string) $values['slug'])
                ->setCountry((string) $values['country'])
                ->setHeroImage((string) $values['image'])
                ->setTagline((string) $values['tagline'])
                ->setRatingSummary((string) $values['rating'], (int) $values['reviews'])
                ->setActivitiesCount((int) $values['count'])
                ->setPriceFrom((int) $values['price'])
                ->setBadge($values['badge'] ?? null)
                ->setPosition($position);

            $manager->persist($destination);
        }

        $manager->flush();
    }
}
