<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Les onze catégories de l'étape 4 de l'assistant « Créer un événement »
 * n'étaient créées que par les fixtures : en production (fixtures jamais
 * chargées) la liste était vide et l'étape impossible à valider.
 * Insertion idempotente : une catégorie déjà présente (même slug) est laissée
 * telle quelle.
 */
final class Version20261012100000 extends AbstractMigration
{
    /** slug => [nom, couleur du badge] — mêmes valeurs que EventFixtures. */
    private const CATEGORIES = [
        'plein-air' => ['Activités de plein air', 'green'],
        'sorties-loisir' => ['Sorties & Loisir', 'blue'],
        'repas-gastronomie' => ['Repas & Gastronomie', 'orange'],
        'bien-etre-sante' => ['Bien-être & Santé', 'violet'],
        'ateliers-apprentissage' => ['Ateliers & Apprentissage', 'blue'],
        'soirees-fetes' => ['Soirées & Fêtes', 'violet'],
        'rencontres-echanges' => ['Rencontres & Échanges', 'blue'],
        'culture-arts' => ['Culture & Arts', 'orange'],
        'voyages-evasion' => ['Voyages & Évasion', 'blue'],
        'actions-solidaires' => ['Actions solidaires', 'green'],
        'autre' => ['Autre', 'navy'],
    ];

    public function getDescription(): string
    {
        return "Catégories de l'assistant de création d'événement (étape 4) en base";
    }

    public function up(Schema $schema): void
    {
        $position = 100;
        foreach (self::CATEGORIES as $slug => [$name, $color]) {
            $this->addSql(
                'INSERT INTO event_category (id, name, slug, color, position, created_at, updated_at)
                 VALUES (gen_random_uuid(), :name, :slug, :color, :position, NOW(), NOW())
                 ON CONFLICT (slug) DO NOTHING',
                ['name' => $name, 'slug' => $slug, 'color' => $color, 'position' => $position++],
            );
        }
    }

    public function down(Schema $schema): void
    {
        // Rien : des événements peuvent déjà pointer vers ces catégories.
    }
}
