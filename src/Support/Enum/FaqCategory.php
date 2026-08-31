<?php

declare(strict_types=1);

namespace App\Support\Enum;

/**
 * Rubrique d'une question fréquente.
 *
 * POURQUOI UNE ÉNUMÉRATION ET NON UN CHAMP LIBRE
 * La rubrique n'est pas qu'une étiquette : le Centre d'aide en fait des cartes,
 * avec une icône et une phrase de présentation chacune. Un champ libre ferait
 * apparaître une carte sans icône dès qu'un administrateur saisirait une
 * rubrique inconnue, et rendrait la traduction anglaise impossible à tenir.
 *
 * Conséquence assumée : AJOUTER UNE RUBRIQUE DEMANDE UNE MODIFICATION DE CODE.
 * Ajouter une QUESTION, en revanche — ce qui arrive tous les jours — se fait
 * entièrement depuis le back-office. C'est le partage voulu : le contenu est en
 * base, la structure reste dans le code.
 */
enum FaqCategory: string
{
    case Booking = 'booking';
    case Payment = 'payment';
    case Account = 'account';
    case Activities = 'activities';
    case Gifts = 'gifts';
    case Providers = 'providers';

    public function label(): string
    {
        return match ($this) {
            self::Booking => 'Réservations',
            self::Payment => 'Paiement et remboursement',
            self::Account => 'Mon compte',
            self::Activities => 'Activités et événements',
            self::Gifts => 'Bons cadeaux',
            self::Providers => 'Prestataires',
        };
    }

    /**
     * Phrase affichée sous le titre de la rubrique, sur le Centre d'aide.
     */
    public function description(): string
    {
        return match ($this) {
            self::Booking => 'Réserver, modifier ou annuler une réservation.',
            self::Payment => 'Moyens de paiement, factures et remboursements.',
            self::Account => 'Inscription, connexion, données personnelles.',
            self::Activities => 'Trouver une activité, participer à un événement.',
            self::Gifts => 'Offrir, recevoir et utiliser un bon cadeau.',
            self::Providers => 'Proposer ses activités sur la plateforme.',
        };
    }

    /**
     * Nom de l'icône du jeu `_icons.html.twig` associée à la rubrique.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Booking => 'calendar',
            self::Payment => 'receipt',
            self::Account => 'gear',
            self::Activities => 'cat_hiking',
            self::Gifts => 'gift',
            self::Providers => 'badge_check',
        };
    }

    /**
     * Ordre d'affichage des rubriques, du plus au moins demandé.
     *
     * @return list<self>
     */
    public static function ordered(): array
    {
        return [
            self::Booking,
            self::Payment,
            self::Account,
            self::Activities,
            self::Gifts,
            self::Providers,
        ];
    }
}
