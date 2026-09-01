<?php

declare(strict_types=1);

namespace App\Catalog\Enum;

/**
 * Critères de tri du catalogue d'activités.
 *
 * POURQUOI CES QUATRE-LÀ, ET PAS D'AUTRES
 * La maquette n'affiche qu'un libellé, « Les plus populaires », et ne dessine
 * aucun menu déroulant : elle ne dit donc pas ce que le tri propose. Ces quatre
 * critères sont les seuls que les données réelles permettent de calculer
 * aujourd'hui — note, nombre d'avis et prix des formules. Proposer « les plus
 * proches » ou « les mieux notées cette semaine » demanderait des données que
 * personne ne saisit.
 *
 * LA VALEUR EST CE QUI PASSE DANS L'URL : elle est donc lisible et stable, pour
 * qu'un tri se partage et survive au bouton Précédent.
 */
enum ActivitySort: string
{
    case Popular = 'populaires';
    case PriceAsc = 'prix-asc';
    case PriceDesc = 'prix-desc';
    case Rating = 'note';

    public function label(): string
    {
        return match ($this) {
            self::Popular => 'Les plus populaires',
            self::PriceAsc => 'Prix croissant',
            self::PriceDesc => 'Prix décroissant',
            self::Rating => 'Les mieux notées',
        };
    }

    /**
     * Le tri porte-t-il sur le prix ?
     *
     * Le prix ne vit pas sur l'activité mais sur ses formules : ces deux
     * critères imposent une requête différente des autres, avec un
     * regroupement et un MIN(). Le repository a besoin de le savoir.
     */
    public function isByPrice(): bool
    {
        return self::PriceAsc === $this || self::PriceDesc === $this;
    }

    /**
     * Le tri par défaut, celui que la maquette affiche.
     */
    public static function default(): self
    {
        return self::Popular;
    }

    /**
     * Lit une valeur venue de l'URL.
     *
     * Une valeur inconnue — adresse tapée à la main, vieux lien — ne doit pas
     * produire d'erreur : on retombe sur le tri par défaut, comme si rien
     * n'avait été demandé.
     */
    public static function fromRequest(?string $valeur): self
    {
        return self::tryFrom(trim((string) $valeur)) ?? self::default();
    }

    /**
     * @return list<self>
     */
    public static function ordered(): array
    {
        return [self::Popular, self::PriceAsc, self::PriceDesc, self::Rating];
    }
}
