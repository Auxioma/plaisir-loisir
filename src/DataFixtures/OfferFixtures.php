<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Catalog\Entity\Promotion;
use App\Catalog\Entity\Service;
use App\Catalog\Enum\PromotionKind;
use App\Catalog\Enum\ServiceStatus;
use App\Catalog\Repository\PromotionRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Offres en cours sur les activités publiées qui n'en ont pas encore (une
 * seule par activité : la réservation applique la meilleure offre en cours,
 * la carte doit afficher la même), pour que la page « Offres du moment »
 * (maquette offres_moments.jpeg) ait de la matière.
 * Dates relatives : les offres restent « en cours » quel que soit le jour du
 * chargement.
 */
final class OfferFixtures extends Fixture implements DependentFixtureInterface
{
    public function __construct(
        private readonly PromotionRepository $promotions,
    ) {
    }

    public function getDependencies(): array
    {
        return [CatalogFixtures::class, ProviderSpaceFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        $services = array_values(array_filter(
            $manager->getRepository(Service::class)->findBy(['status' => ServiceStatus::Published], ['title' => 'ASC']),
            fn (Service $s): bool => null !== $s->getProvider() && null === $this->promotions->findRunningForService($s),
        ));

        // [titre, sous-titre, type, %, début (jours), fin (jours), heure de fin]
        $offers = [
            ['Offre exclusive -30%', 'Places limitées', PromotionKind::Reduction, 30, -3, 2, 14],
            ['Réduction 25%', 'Pour toute réservation cette semaine', PromotionKind::Reduction, 25, -2, 1, 8],
            ['Réduction 20%', 'Offre de saison', PromotionKind::Reduction, 20, -6, 3, 12],
            ['Réduction 15%', 'Réservation anticipée', PromotionKind::Reduction, 15, -1, 4, 9],
            ['Dernière minute -35%', 'Aujourd’hui seulement', PromotionKind::Reduction, 35, -1, 0, 23],
            ['Réduction 20%', 'Offre découverte', PromotionKind::Reduction, 20, -4, 1, 20],
            ['Réduction 40%', 'Vente flash', PromotionKind::Reduction, 40, 0, 1, 23],
            ['2 pour 1', 'Venez à deux, payez pour un', PromotionKind::TwoForOne, null, -5, 10, 23],
            ['Offre spéciale', 'Boisson de bienvenue offerte', PromotionKind::Special, null, -2, 12, 23],
            ['Réduction 10%', 'Nouveaux membres', PromotionKind::Reduction, 10, -1, 20, 23],
        ];

        $today = new \DateTimeImmutable('today');
        foreach ($offers as $i => [$title, $subtitle, $kind, $percent, $start, $end, $hour]) {
            $service = $services[$i] ?? null;
            if (null === $service || null === $service->getProvider()) {
                break;
            }
            $manager->persist((new Promotion())
                ->setProvider($service->getProvider())
                ->setService($service)
                ->setTitle($title)
                ->setSubtitle($subtitle)
                ->setKind($kind)
                ->setDiscountPercent($percent)
                ->setStartsAt($today->modify(sprintf('%+d days', $start)))
                ->setEndsAt($today->modify(sprintf('%+d days', $end))->setTime($hour, 59, 59)));
        }

        $manager->flush();
    }
}
