<?php

declare(strict_types=1);

namespace App\Command;

use App\Catalog\Enum\ServiceStatus;
use App\Catalog\Repository\DestinationRepository;
use App\Catalog\Repository\ServiceRepository;
use App\Search\CatalogIndexer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Reconstruit l'index Elasticsearch à partir de PostgreSQL (source de vérité).
 */
#[AsCommand(name: 'app:search:reindex', description: 'Réindexe le catalogue dans Elasticsearch.')]
final class ReindexCatalogCommand extends Command
{
    public function __construct(
        private readonly CatalogIndexer $indexer,
        private readonly ServiceRepository $services,
        private readonly DestinationRepository $destinations,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->indexer->createIndices();

        $activities = 0;
        foreach ($this->services->findBy(['status' => ServiceStatus::Published]) as $service) {
            $this->indexer->indexService($service);
            ++$activities;
        }

        $destinations = 0;
        foreach ($this->destinations->findAll() as $destination) {
            $this->indexer->indexDestination($destination);
            ++$destinations;
        }

        $output->writeln(\sprintf('Réindexé : %d activités, %d destinations.', $activities, $destinations));

        return Command::SUCCESS;
    }
}
