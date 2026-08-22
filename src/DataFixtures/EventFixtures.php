<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Event\Entity\Event;
use App\Event\Entity\EventCategory;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Les douze événements de la maquette, mis en base.
 *
 * Contenu repris MOT POUR MOT de App\Event\StaticEvents, comme pour le
 * catalogue : les dix écrans du parcours Événements sont calés au pixel.
 *
 * Les libellés « 15 Mai 2026 » et « 9h00 - 16h00 » sont convertis en vraies
 * dates. Le sens de la conversion compte : d'une date on refait le libellé,
 * l'inverse est impossible — et sans date exploitable, l'écran calendrier ne
 * peut placer aucun événement dans une case.
 *
 * La maquette répète plusieurs fois les mêmes intitulés (trois « Match de foot
 * amical », trois « Barbecue entre amis »). Ce sont des cartes de remplissage :
 * on les reproduit telles quelles, avec des slugs distincts puisqu'une adresse
 * ne peut pas désigner deux événements.
 */
class EventFixtures extends Fixture
{
    /**
     * Catégories des badges, avec la couleur que la maquette leur donne.
     *
     * À ne pas confondre avec les pastilles de navigation (Canoë/Kayak,
     * VTT/Vélo…), qui forment une autre liste, éditoriale.
     *
     * @var array<string, array{name: string, color: string}>
     */
    private const CATEGORIES = [
        'sports' => ['name' => 'Sports', 'color' => 'blue'],
        'repas' => ['name' => 'Repas', 'color' => 'orange'],
        'bien-etre' => ['name' => 'Bien-être', 'color' => 'violet'],
        'randonnee' => ['name' => 'Randonnée', 'color' => 'green'],
        'en-famille' => ['name' => 'En famille', 'color' => 'green'],
        'culture' => ['name' => 'Culture', 'color' => 'orange'],
        'jeu' => ['name' => 'Jeu', 'color' => 'navy'],
        'loisirs' => ['name' => 'Loisirs', 'color' => 'violet'],

        // Les onze categories que propose l'assistant de creation. Elles ne
        // recoupent AUCUNE des huit precedentes : la maquette entretient trois
        // listes de categories distinctes dans le meme parcours (badges des
        // cartes, pastilles de navigation, choix de l'assistant). Elles sont
        // reunies dans la meme table, seul modele coherent — a defaut de quoi
        // un evenement cree par l'assistant n'aurait pas de badge.
        //
        // La couleur n'est pas precisee par la maquette pour celles-ci : elles
        // prennent le bleu, couleur par defaut du badge. A faire trancher.
        'plein-air' => ['name' => 'Activités de plein air', 'color' => 'green'],
        'sorties-loisir' => ['name' => 'Sorties & Loisir', 'color' => 'blue'],
        'repas-gastronomie' => ['name' => 'Repas & Gastronomie', 'color' => 'orange'],
        'bien-etre-sante' => ['name' => 'Bien-être & Santé', 'color' => 'violet'],
        'ateliers-apprentissage' => ['name' => 'Ateliers & Apprentissage', 'color' => 'blue'],
        'soirees-fetes' => ['name' => 'Soirées & Fêtes', 'color' => 'violet'],
        'rencontres-echanges' => ['name' => 'Rencontres & Échanges', 'color' => 'blue'],
        'culture-arts' => ['name' => 'Culture & Arts', 'color' => 'orange'],
        'voyages-evasion' => ['name' => 'Voyages & Évasion', 'color' => 'blue'],
        'actions-solidaires' => ['name' => 'Actions solidaires', 'color' => 'green'],
        'autre' => ['name' => 'Autre', 'color' => 'navy'],
    ];

    /**
     * @var list<array<string, mixed>>
     */
    private const EVENTS = [
        ['title' => 'Compétitions  Canoë / Kayak', 'slug' => 'competitions-canoe-kayak', 'categorie' => 'sports', 'image' => 'images/events/ev-raft-clean.jpg', 'lieu' => 'Autrans, 38880', 'debut' => '2026-05-15 09:00', 'fin' => '2026-05-15 16:00', 'participants' => 12],
        ['title' => 'Match de foot amical', 'slug' => 'match-de-foot-amical', 'categorie' => 'sports', 'image' => 'images/events/ev-foot-clean.jpg', 'lieu' => 'Toulouse, 31000', 'debut' => '2026-05-15 10:00', 'fin' => '2026-05-15 12:00', 'participants' => 8],
        ['title' => 'Barbecue entre amis', 'slug' => 'barbecue-entre-amis', 'categorie' => 'repas', 'image' => 'images/events/ev-bbq-clean.jpg', 'lieu' => 'Lyon, 69000', 'debut' => '2026-05-18 12:00', 'fin' => '2026-05-18 18:00', 'participants' => 12],
        ['title' => 'Séance de yoga en plein air', 'slug' => 'seance-de-yoga-en-plein-air', 'categorie' => 'bien-etre', 'image' => 'images/events/ev-yoga-clean.jpg', 'lieu' => 'Nantes, 44000', 'debut' => '2026-06-02 10:00', 'fin' => '2026-06-02 11:30', 'participants' => 12],
        ['title' => 'Randonnée dans le Vercors', 'slug' => 'randonnee-dans-le-vercors', 'categorie' => 'randonnee', 'image' => 'images/events/ev-rando-clean.jpg', 'lieu' => 'Autrans, 38880', 'debut' => '2026-05-15 09:00', 'fin' => '2026-05-15 16:00', 'participants' => 12],
        ['title' => 'Match de foot amical', 'slug' => 'match-de-foot-amical-2', 'categorie' => 'sports', 'image' => 'images/events/ev-foot-clean.jpg', 'lieu' => 'Toulouse, 31000', 'debut' => '2026-05-15 10:00', 'fin' => '2026-05-15 12:00', 'participants' => 8],
        ['title' => 'Barbecue entre amis', 'slug' => 'barbecue-entre-amis-2', 'categorie' => 'repas', 'image' => 'images/events/ev-bbq-clean.jpg', 'lieu' => 'Lyon, 69000', 'debut' => '2026-05-18 12:00', 'fin' => '2026-05-18 18:00', 'participants' => 12],
        ['title' => 'Séance de yoga en plein air', 'slug' => 'seance-de-yoga-en-plein-air-2', 'categorie' => 'bien-etre', 'image' => 'images/events/ev-yoga-clean.jpg', 'lieu' => 'Nantes, 44000', 'debut' => '2026-06-02 10:00', 'fin' => '2026-06-02 11:30', 'participants' => 12],
        // Les deux suivants ont leur catégorie INVERSÉE entre l'écran d'accueil
        // (« Culture » puis « En famille ») et le listing (« En famille » puis
        // « Culture »). On retient celle du listing, qui sert deux écrans sur
        // trois. Incohérence de la maquette, à signaler.
        ['title' => 'Barbecue entre amis', 'slug' => 'barbecue-entre-amis-3', 'categorie' => 'en-famille', 'image' => 'images/events/ev-famille.jpg', 'lieu' => 'Autrans, 38880', 'debut' => '2026-05-15 09:00', 'fin' => '2026-05-15 16:00', 'participants' => 12],
        ['title' => 'Randonnée dans le Vercors', 'slug' => 'randonnee-dans-le-vercors-2', 'categorie' => 'culture', 'image' => 'images/events/ev-catacombes.jpg', 'lieu' => 'Lyon, 69000', 'debut' => '2026-05-18 12:00', 'fin' => '2026-05-18 18:00', 'participants' => 12],
        ['title' => 'Match de foot amical', 'slug' => 'match-de-foot-amical-3', 'categorie' => 'jeu', 'image' => 'images/events/ev-foot-clean.jpg', 'lieu' => 'Toulouse, 31000', 'debut' => '2026-05-15 10:00', 'fin' => '2026-05-15 12:00', 'participants' => 8],
        ['title' => 'Séance de yoga en plein air', 'slug' => 'seance-de-yoga-en-plein-air-3', 'categorie' => 'loisirs', 'image' => 'images/events/ev-yoga-clean.jpg', 'lieu' => 'Nantes, 44000', 'debut' => '2026-06-02 10:00', 'fin' => '2026-06-02 11:30', 'participants' => 12],
    ];

    public function load(ObjectManager $manager): void
    {
        $categories = [];
        $position = 1;

        foreach (self::CATEGORIES as $slug => $data) {
            $category = new EventCategory();
            $category->setName($data['name'])->setSlug($slug)->setColor($data['color'])->setPosition($position++);
            $manager->persist($category);

            $categories[$slug] = $category;
        }

        foreach (self::EVENTS as $rang => $data) {
            $event = new Event();
            $event
                ->setTitle((string) $data['title'])
                ->setSlug((string) $data['slug'])
                ->setCategory($categories[$data['categorie']])
                ->setImagePath((string) $data['image'])
                ->setLocation((string) $data['lieu'])
                ->setStartsAt(new \DateTimeImmutable((string) $data['debut']))
                ->setEndsAt(new \DateTimeImmutable((string) $data['fin']))
                ->setParticipantsCount((int) $data['participants'])
                ->setPosition($rang);

            $manager->persist($event);
        }

        $manager->flush();
    }
}
