<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Event\Entity\Group;
use App\Event\Entity\GroupAlbum;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Les seize groupes de la maquette et les douze albums de l'onglet Photos.
 *
 * Contenu repris MOT POUR MOT de App\Event\StaticEvents.
 *
 * Deux groupes portent le même nom (« Soul & Flow yoga ») avec des photos
 * différentes : le nom affiché reste identique, le slug ne le peut pas.
 *
 * Les albums sont tous rattachés au premier groupe : la maquette ne montre
 * qu'une seule page de détail de groupe, celle qui porte ces douze albums.
 */
class GroupFixtures extends Fixture
{
    /** Description de remplissage, répétée telle quelle par la maquette. */
    private const LOREM = 'Description du groupe simply dummy text of the printing and typesetting industry. Lorem Ipsum';

    /**
     * @var list<array<string, mixed>>
     */
    private const GROUPS = [
        ['name' => 'Cours collectifs de fitness à Lyon', 'slug' => 'cours-collectifs-de-fitness-a-lyon', 'image' => 'images/events/grp-fitness.jpg', 'lieu' => 'Lyon, 38880', 'membres' => 5246, 'badge' => null, 'description' => 'Vous voulez bouger, vous amuser et retrouver la forme ?Nous animons ce groupe pour promouvoir des séances…'],
        ['name' => 'Retour à la nature', 'slug' => 'retour-a-la-nature', 'image' => 'images/events/grp-nature.jpg', 'lieu' => 'Toulouse, 31000', 'membres' => 4562, 'badge' => null],
        ['name' => 'Grenoble aventure club', 'slug' => 'grenoble-aventure-club', 'image' => 'images/events/grp-grenoble.jpg', 'lieu' => 'Grenoble , France', 'membres' => 124, 'badge' => null],
        ['name' => 'Soul & Flow yoga', 'slug' => 'soul-flow-yoga', 'image' => 'images/events/ev-yoga-clean.jpg', 'lieu' => 'Nantes, 44000', 'membres' => 356, 'badge' => null],
        ['name' => 'Paris Art Community', 'slug' => 'paris-art-community', 'image' => 'images/events/grp-art.jpg', 'lieu' => 'Autrans, 38880', 'membres' => 6842, 'badge' => null],
        ['name' => 'Club de lecture', 'slug' => 'club-de-lecture', 'image' => 'images/events/grp-lecture.jpg', 'lieu' => 'Dijon, 69000', 'membres' => 254, 'badge' => null],
        ['name' => 'Culture & Conversation Café', 'slug' => 'culture-conversation-cafe', 'image' => 'images/events/grp-culturecafe.jpg', 'lieu' => 'Toulouse, 31000', 'membres' => 3541, 'badge' => null],
        ['name' => 'Soul & Flow yoga', 'slug' => 'soul-flow-yoga-2', 'image' => 'images/events/grp-spa.jpg', 'lieu' => 'Nantes, 44000', 'membres' => 356, 'badge' => null],
        ['name' => 'Les amoureux de nourritures', 'slug' => 'les-amoureux-de-nourritures', 'image' => 'images/events/grp-nourriture.jpg', 'lieu' => 'Autrans, 38880', 'membres' => 254, 'badge' => null],
        ['name' => 'Meet in Paris', 'slug' => 'meet-in-paris', 'image' => 'images/events/grp-meetparis.jpg', 'lieu' => 'Lyon, 69000', 'membres' => 12, 'badge' => null, 'description' => "Sorties & Soirées uniques à Paris 🌟\nPour les 24-40 ans\nRestez connectés"],
        ['name' => 'Speed dating - Love et amitié', 'slug' => 'speed-dating-love-et-amitie', 'image' => 'images/events/grp-speeddating.jpg', 'lieu' => 'Nantes, 44000', 'membres' => 1095, 'badge' => 'Nouveau'],
        ['name' => 'Randonnée dans le Vercors', 'slug' => 'groupe-randonnee-dans-le-vercors', 'image' => 'images/events/ev-catacombes.jpg', 'lieu' => 'Lyon, 69000', 'membres' => 12, 'badge' => null],
        ['name' => 'Sortie 20 - 45 ans Paris', 'slug' => 'sortie-20-45-ans-paris', 'image' => 'images/events/grp-afterwork.jpg', 'lieu' => 'Paris', 'membres' => 224, 'badge' => null, 'description' => "Qui est pour un groupe WhatsApp de 6600 membres pour faire des sorties AMICALES pour les habitants de l'IDF …"],
        ['name' => "Jeu de société - C'est parti !", 'slug' => 'jeu-de-societe-cest-parti', 'image' => 'images/events/grp-jeu.jpg', 'lieu' => 'Paris', 'membres' => 224, 'badge' => null, 'description' => 'Rejoignez le nouveau groupe  pour des soirées de jeu conviviales et détendues ! Tout le monde est le bienvenu: débu…'],
        ['name' => 'Afri-House in Paris', 'slug' => 'afri-house-in-paris', 'image' => 'images/events/grp-afrihouse.jpg', 'lieu' => 'Lyon, 69000', 'membres' => 12, 'badge' => 'Nouveau', 'description' => "Bienvenue sur le groupe « Afro-house à Paris » ! Ce groupe s'adresse à tous ceux qui aiment la musique afro-hou…"],
        ['name' => 'Footballers de Nantes', 'slug' => 'footballers-de-nantes', 'image' => 'images/events/grp-footstreet.jpg', 'lieu' => 'Nantes, 44000', 'membres' => 154, 'badge' => null, 'description' => 'Bienvenue chez FootballersNantes, une communauté ouverte à tous ceux qui aiment jouer au football dans une am…'],
    ];

    /**
     * @var list<array{title: string, lieu: string, image: string}>
     */
    private const ALBUMS = [
        ['title' => 'Weekend spa  & bien-être', 'lieu' => 'Paris', 'image' => 'images/events/alb-spa.jpg'],
        ['title' => 'visite de musée de Louve', 'lieu' => 'Bordeaux', 'image' => 'images/events/alb-louvre.jpg'],
        ['title' => 'Spectacles à Disneyland', 'lieu' => 'Paris', 'image' => 'images/events/alb-disney.jpg'],
        ['title' => 'À la Découverte de nos Terroirs', 'lieu' => 'Nice', 'image' => 'images/events/alb-vins.jpg'],
        ['title' => 'Initiation au brassage de la bière', 'lieu' => 'Dijon', 'image' => 'images/events/alb-biere.jpg'],
        ['title' => 'Foodies + New Friends', 'lieu' => 'Lyon', 'image' => 'images/events/grp-nourriture.jpg'],
        ['title' => 'Championnat de Canoë-Kayak', 'lieu' => 'Lille', 'image' => 'images/events/alb-canoerouge.jpg'],
        ['title' => 'Visite Du Vulcania', 'lieu' => "Pays des volcans d'Auvergne", 'image' => 'images/events/alb-vulcania.jpg'],
        ['title' => 'Atelier pour enfant', 'lieu' => 'Nantes', 'image' => 'images/events/alb-louvre.jpg'],
        ['title' => 'Soirée karaoké', 'lieu' => 'Seine', 'image' => 'images/events/alb-karaoke.jpg'],
        ['title' => 'Excursion en bateau', 'lieu' => "Côte d'Azur", 'image' => 'images/events/alb-seine.jpg'],
        ['title' => 'Week-end création parfum', 'lieu' => "Pays des volcans d'Auvergne", 'image' => 'images/events/alb-parfum.jpg'],
    ];

    public function load(ObjectManager $manager): void
    {
        $premier = null;

        foreach (self::GROUPS as $rang => $data) {
            $group = new Group();
            $group
                ->setName((string) $data['name'])
                ->setSlug((string) $data['slug'])
                ->setImagePath((string) $data['image'])
                ->setLocation((string) $data['lieu'])
                ->setMembersCount((int) $data['membres'])
                ->setBadge($data['badge'] ?? null)
                ->setDescription((string) ($data['description'] ?? self::LOREM))
                ->setPosition($rang);

            $manager->persist($group);

            $premier ??= $group;
        }

        // La liste des groupes est une constante non vide : $premier est
        // forcement renseigne apres la boucle, inutile de s'en assurer.
        //
        // « Mis à jour le 28 Juill. 2026 » : la maquette affiche la même date
        // sur les douze albums.
        $miseAJour = new \DateTimeImmutable('2026-07-28 12:00');

        foreach (self::ALBUMS as $rang => $data) {
            $album = new GroupAlbum();
            $album
                ->setTitle($data['title'])
                ->setLocation($data['lieu'])
                ->setImagePath($data['image'])
                ->setPhotosCount(5)
                ->setLastPhotoAt($miseAJour)
                ->setPosition($rang);

            $premier->addAlbum($album);
            $manager->persist($album);
        }

        $manager->flush();
    }
}
