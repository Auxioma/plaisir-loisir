<?php

declare(strict_types=1);

namespace App\Corporate\Command;

use App\Corporate\Service\CorporateContentImporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Charge en base le contenu des pages institutionnelles (valeurs, équipe,
 * témoignages, étapes, offres d'emploi…).
 *
 *   php bin/console app:contenu:importer            # charge ce qui manque
 *   php bin/console app:contenu:importer --dry-run  # montre sans écrire
 *
 * Sans danger en production : seules les sections vides sont remplies, rien
 * n'est jamais écrasé ni supprimé. Lancée à chaque déploiement (cd.yaml).
 */
#[AsCommand(name: 'app:contenu:importer', description: 'Charge le contenu de départ des pages institutionnelles dans les sections vides.')]
final class ImportCorporateContentCommand extends Command
{
    public function __construct(private readonly CorporateContentImporter $importer)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Affiche ce qui serait chargé, sans rien écrire.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        $created = $this->importer->import($dryRun);

        $io->table(['Section', $dryRun ? 'À créer' : 'Créés'], array_map(
            static fn (string $label, int $n): array => [$label, 0 === $n ? '— déjà rempli' : (string) $n],
            array_keys($created),
            $created,
        ));

        $total = array_sum($created);
        if (0 === $total) {
            $io->success('Rien à charger : toutes les sections ont déjà du contenu.');
        } else {
            $io->success(sprintf('%d élément(s) %s.', $total, $dryRun ? 'seraient chargés' : 'chargés'));
        }

        return Command::SUCCESS;
    }
}
