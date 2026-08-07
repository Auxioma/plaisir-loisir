<?php

declare(strict_types=1);

namespace App\I18n\Command;

use App\I18n\Entity\Translation;
use App\I18n\Repository\TranslationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;

/**
 * Importe (upsert) la graine YAML des traductions dans la table `translation`.
 * À rejouer après chaque ajout de clés dans config/i18n/ ; les valeurs
 * modifiées par le client en base ne sont PAS écrasées sans --force.
 */
#[AsCommand(
    name: 'app:i18n:import',
    description: 'Importe la graine config/i18n/messages.{locale}.yaml dans la table translation',
)]
final class ImportTranslationsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TranslationRepository $repository,
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('locale', null, InputOption::VALUE_REQUIRED, 'Locale à importer', 'en')
            ->addOption('domain', null, InputOption::VALUE_REQUIRED, 'Domaine de traduction', 'messages')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Écrase aussi les valeurs déjà modifiées en base');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $locale = (string) $input->getOption('locale');
        $domain = (string) $input->getOption('domain');

        $seed = \sprintf('%s/config/i18n/%s.%s.yaml', $this->projectDir, $domain, $locale);
        if (!is_file($seed)) {
            $io->error(\sprintf('Graine introuvable : %s', $seed));

            return Command::FAILURE;
        }

        /** @var array<string, string> $messages */
        $messages = Yaml::parseFile($seed) ?? [];

        $existing = [];
        foreach ($this->repository->findBy(['locale' => $locale, 'domain' => $domain]) as $row) {
            $existing[$row->getSource()] = $row;
        }

        $created = $updated = $kept = 0;
        foreach ($messages as $source => $translation) {
            $source = (string) $source;
            $translation = (string) $translation;

            if (!isset($existing[$source])) {
                $row = (new Translation())
                    ->setLocale($locale)
                    ->setDomain($domain)
                    ->setSource($source)
                    ->setTranslation($translation);
                $this->entityManager->persist($row);
                ++$created;
            } elseif ($existing[$source]->getTranslation() !== $translation) {
                if ($input->getOption('force')) {
                    $existing[$source]->setTranslation($translation);
                    ++$updated;
                } else {
                    ++$kept; // valeur personnalisée en base, préservée
                }
            }
        }
        $this->entityManager->flush();

        // Invalide le cache du translator : le fichier témoin est la
        // « ressource » surveillée par Symfony.
        $witness = \sprintf('%s/translations/%s.%s.db', $this->projectDir, $domain, $locale);
        touch($witness);

        $io->success(\sprintf(
            '%d créées, %d mises à jour, %d valeurs personnalisées préservées (locale %s). Pensez à vider le cache en prod.',
            $created,
            $updated,
            $kept,
            $locale,
        ));

        return Command::SUCCESS;
    }
}
