<?php

declare(strict_types=1);

namespace App\Provider\Service;

use App\Provider\Entity\ProviderProfile;
use App\Provider\Repository\ProviderProfileRepository;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Génère l'identifiant public d'un profil prestataire (`/professionnels/{slug}`).
 *
 * Pas de callback Doctrine (PrePersist) : le slug doit être lisible dès
 * l'étape 1 de l'inscription (le profil est persisté avant la fin du
 * parcours), et le rendre disponible plus tôt évite toute dépendance à l'ordre
 * d'exécution des listeners Doctrine.
 */
final class ProviderSlugService
{
    public function __construct(
        private readonly SluggerInterface $slugger,
        private readonly ProviderProfileRepository $profiles,
    ) {
    }

    public function assign(ProviderProfile $profile): void
    {
        if (null !== $profile->getSlug()) {
            return;
        }

        $base = strtolower($this->slugger->slug(
            '' !== $profile->getDisplayName() ? $profile->getDisplayName() : 'prestataire',
        )->toString());

        $slug = $base;
        $suffix = 2;

        while ($this->profiles->isSlugTaken($slug, $profile)) {
            $slug = $base.'-'.$suffix;
            ++$suffix;
        }

        $profile->setSlug($slug);
    }
}
