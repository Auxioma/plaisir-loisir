<?php

declare(strict_types=1);

namespace App\Payment\Command;

use App\Payment\Entity\SubscriptionPlan;
use App\Payment\Enum\BillingPeriod;
use App\Payment\Repository\SubscriptionPlanRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Crée les offres d'abonnement prestataire retenues le 20/10 (Essentiel,
 * Pro, Premium — mensuelles). Idempotente : une offre dont le slug existe
 * déjà n'est jamais modifiée (prix et textes se changent ensuite dans
 * EasyAdmin › Offres d'abonnement).
 *
 * Attention : dès qu'une offre ACTIVE existe, les prestataires sans
 * abonnement sont renvoyés vers /pro/abonnement (SubscriptionAccess).
 * --inactives crée les offres désactivées, à activer plus tard dans le
 * back-office.
 */
#[AsCommand(
    name: 'app:subscription:plans',
    description: 'Crée les offres d\'abonnement prestataire (Essentiel, Pro, Premium) si elles n\'existent pas.',
)]
final class SeedSubscriptionPlansCommand extends Command
{
    /** @var list<array{slug: string, name: string, price: string, description: string, featured: bool, stats: bool}> */
    private const PLANS = [
        ['slug' => 'essentiel', 'name' => 'Essentiel', 'price' => '19.00', 'featured' => false, 'stats' => false,
            'description' => 'Accès complet à l’espace professionnel : publiez vos activités et recevez vos réservations.'],
        ['slug' => 'pro', 'name' => 'Pro', 'price' => '39.00', 'featured' => true, 'stats' => false,
            'description' => 'Tout Essentiel, avec vos activités mises en avant dans les résultats de recherche.'],
        ['slug' => 'premium', 'name' => 'Premium', 'price' => '69.00', 'featured' => true, 'stats' => true,
            'description' => 'Tout Pro, avec les statistiques avancées pour piloter votre activité.'],
    ];

    public function __construct(
        private readonly SubscriptionPlanRepository $plans,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('inactives', null, InputOption::VALUE_NONE, 'Créer les offres désactivées (le verrou d\'abonnement ne s\'applique pas encore)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $active = !$input->getOption('inactives');

        foreach (self::PLANS as $position => $p) {
            if (null !== $this->plans->findOneBy(['slug' => $p['slug']])) {
                $io->writeln(sprintf(' · « %s » existe déjà : inchangée.', $p['name']));
                continue;
            }

            $this->entityManager->persist((new SubscriptionPlan())
                ->setName($p['name'])
                ->setSlug($p['slug'])
                ->setDescription($p['description'])
                ->setBillingPeriod(BillingPeriod::Monthly)
                ->setPriceAmount($p['price'])
                ->setCurrency('EUR')
                ->setFeatured($p['featured'])
                ->setAdvancedStatistics($p['stats'])
                ->setActive($active)
                ->setPosition($position));
            $io->writeln(sprintf(' + « %s » créée : %s € / mois%s.', $p['name'], str_replace('.', ',', $p['price']), $active ? '' : ' (désactivée)'));
        }

        $this->entityManager->flush();

        $io->success($active
            ? 'Offres en place. Les prestataires sans abonnement sont désormais renvoyés vers /pro/abonnement.'
            : 'Offres créées désactivées : activez-les dans EasyAdmin pour ouvrir les abonnements.');

        return Command::SUCCESS;
    }
}
