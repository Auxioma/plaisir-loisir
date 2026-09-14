<?php

declare(strict_types=1);

namespace App\Provider\Command;

use App\Provider\Repository\ProviderProfileRepository;
use App\Provider\Service\ProviderSlugService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Attribue un slug aux profils prestataires créés avant son existence.
 *
 * Idempotente : ne touche que les profils dont le slug est encore vide.
 */
#[AsCommand(
    name: 'app:provider:backfill-slugs',
    description: 'Génère l\'adresse publique (slug) des profils prestataires qui n\'en ont pas encore.',
)]
final class BackfillProviderSlugsCommand extends Command
{
    public function __construct(
        private readonly ProviderProfileRepository $profiles,
        private readonly ProviderSlugService $slugService,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $sansSlug = $this->profiles->findBy(['slug' => null]);

        foreach ($sansSlug as $profile) {
            $this->slugService->assign($profile);
            // Un flush par profil, et non un seul à la fin : ProviderSlugService
            // vérifie l'unicité en base. Sans ce flush intermédiaire, deux
            // profils homonymes du lot (ex. deux « Paul Riviere » de fixtures
            // de test) ne se voient pas l'un l'autre et calculent le MÊME
            // slug — la contrainte unique casse alors sur le second.
            $this->entityManager->flush();
            $io->text(sprintf('· %s → /professionnels/%s', $profile->getDisplayName(), (string) $profile->getSlug()));
        }

        $io->success(sprintf('%d profil(s) mis à jour.', \count($sansSlug)));

        return Command::SUCCESS;
    }
}
