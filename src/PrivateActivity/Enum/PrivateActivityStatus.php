<?php

declare(strict_types=1);

namespace App\PrivateActivity\Enum;

/**
 * Cycle de vie d'une activité privée.
 *
 * Simplifié par rapport au workflow indicatif du CDC (§24.2 : DRAFT ->
 * PENDING_MODERATION -> PUBLISHED -> FULL -> IN_PROGRESS -> COMPLETED, avec
 * CANCELLED/EXPIRED/ARCHIVED). Aucun brouillon ni modération n'existe encore
 * (comme ServiceRequest, qui n'a pas non plus de DRAFT) : une activité est
 * publiée dès sa création. La distinction IN_PROGRESS/COMPLETED/EXPIRED,
 * liée à la date de l'activité, est laissée à une itération suivante.
 */
enum PrivateActivityStatus: string
{
    /**
     * Brouillon de l'assistant (05/10) : visible du seul organisateur, absent
     * de toutes les listes, à reprendre depuis « Mes activités créées ».
     */
    case Draft = 'draft';

    /**
     * Publiée par l'organisateur avec une catégorie qu'il a proposée (12/10,
     * demande du client) : il n'attend pas la validation pour tout remplir.
     * Invisible comme un brouillon ; passe en ligne dès que l'équipe valide
     * la catégorie, redevient brouillon si elle la refuse
     * (CategorySuggestionService).
     */
    case PendingCategory = 'pending_category';

    /** Ouverte : accepte des demandes de participation. */
    case Open = 'open';

    /** Capacité maximale atteinte : n'accepte plus de nouvelle place directe. */
    case Full = 'full';

    /** Annulée par l'organisateur (ou par l'administration). */
    case Cancelled = 'cancelled';

    /**
     * Retirée du site par l'administration (back-office, 26/11) : invisible
     * comme un brouillon, plus aucune inscription, et l'organisateur ne peut
     * ni la modifier ni la republier. Seule l'équipe la rétablit.
     */
    case Suspended = 'suspended';

    /**
     * Pas (ou plus) en ligne : visible du seul organisateur, absente des
     * listes. Inclut la suspension par l'administration.
     */
    public function isUnpublished(): bool
    {
        return self::Draft === $this || self::PendingCategory === $this || self::Suspended === $this;
    }

    /**
     * Statuts jamais proposés dans les listes de découverte.
     *
     * @return list<self>
     */
    public static function hiddenFromListings(): array
    {
        return [self::Cancelled, self::Draft, self::PendingCategory, self::Suspended];
    }
}
