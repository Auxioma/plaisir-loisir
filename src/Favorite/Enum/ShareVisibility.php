<?php

declare(strict_types=1);

namespace App\Favorite\Enum;

/**
 * Visibilité d'un partage de favoris (modes de la maquette).
 */
enum ShareVisibility: string
{
    case Private = 'private';      // Privée : accessible uniquement via le lien
    case Public = 'public';        // Publique : accessible à tous
    case Community = 'community';  // Communauté : mis en avant auprès de la communauté
}
