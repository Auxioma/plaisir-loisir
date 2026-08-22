<?php

declare(strict_types=1);

namespace App\Legal\Command;

use App\Corporate\StaticCorporate;
use App\Legal\Enum\LegalDocumentType;
use App\Legal\Repository\LegalDocumentRepository;
use App\Legal\Service\LegalDocumentService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Publie en base la première version des textes juridiques.
 *
 * Le texte n'est pas inventé ici : il est repris MOT POUR MOT de
 * StaticCorporate, c'est-à-dire de ce que le site affiche déjà sur
 * /conditions-generales et /mentions-legales.
 *
 * Les deux documents dont aucun texte n'existe — la politique de
 * confidentialité et la politique de cookies — ne sont PAS publiés. Rédiger du
 * droit à la place du client serait au mieux inutile, au pire dangereux : ces
 * textes engagent l'éditeur. La commande le signale au lieu de combler le vide.
 *
 * Tant que la politique de confidentialité n'est pas publiée, l'inscription
 * enregistre le consentement aux seules CGU. Le jour où elle le sera, les
 * inscriptions suivantes l'enregistreront aussi, sans changement de code.
 */
#[AsCommand(
    name: 'app:legal:publish',
    description: 'Publie la première version des textes juridiques à partir du contenu affiché sur le site.',
)]
final class PublishLegalDocumentsCommand extends Command
{
    private const INITIAL_VERSION = '1.0';

    public function __construct(
        private readonly LegalDocumentService $documentService,
        private readonly LegalDocumentRepository $documents,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        // « doc-version » et non « version » : la console Symfony réserve déjà
        // --version pour afficher la sienne, et la collision fait échouer la
        // commande au démarrage.
        $this
            ->addOption('doc-version', null, InputOption::VALUE_REQUIRED, 'Numéro de version à publier', self::INITIAL_VERSION)
            ->addOption('locale', null, InputOption::VALUE_REQUIRED, 'Langue du texte', 'fr');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $version = (string) $input->getOption('doc-version');
        $locale = (string) $input->getOption('locale');

        $aPublier = [
            [
                'type' => LegalDocumentType::TermsOfService,
                'titre' => 'Conditions générales d\'utilisation',
                'contenu' => $this->renderSections(StaticCorporate::cguSections()),
            ],
            [
                'type' => LegalDocumentType::LegalNotice,
                'titre' => 'Mentions légales',
                'contenu' => $this->renderSections(StaticCorporate::legalSections()),
            ],
        ];

        $publies = 0;

        foreach ($aPublier as $doc) {
            $type = $doc['type'];

            if (null !== $this->documents->findOneByVersion($type, $version, $locale)) {
                $io->text(sprintf('· %s %s (%s) : déjà en base, rien à faire.', $type->label(), $version, $locale));

                continue;
            }

            $this->documentService->publish(
                type: $type,
                version: $version,
                title: (string) $doc['titre'],
                content: (string) $doc['contenu'],
                locale: $locale,
                changeSummary: 'Première mise en base du texte déjà publié sur le site.',
            );

            ++$publies;
            $io->text(sprintf('✓ %s %s (%s) publié.', $type->label(), $version, $locale));
        }

        $manquants = [];
        foreach ([LegalDocumentType::PrivacyPolicy, LegalDocumentType::CookiePolicy, LegalDocumentType::TermsOfSale] as $type) {
            if (null === $this->documents->findCurrent($type, $locale)) {
                $manquants[] = $type->label();
            }
        }

        if ([] !== $manquants) {
            $io->warning(sprintf(
                "Aucun texte n'existe pour : %s.\n".
                "Ces documents ne sont pas publiés : leur rédaction relève du client, pas du code.\n".
                "Conséquence immédiate : l'inscription n'enregistre pas de consentement pour eux, et les connexions Google et Facebook exigeront une politique de confidentialité en ligne.",
                implode(', ', $manquants),
            ));
        }

        $io->success(sprintf('%d document(s) publié(s).', $publies));

        return Command::SUCCESS;
    }

    /**
     * Aplatit les sections de StaticCorporate en un texte lisible.
     *
     * Chaque section porte un titre, une éventuelle introduction, une liste
     * d'« items » (l'éditeur du site, l'hébergeur…) et des « paragraphs » (le
     * corps des CGU). On traite les trois formes plutôt que d'en normaliser une
     * dans StaticCorporate, qui alimente aussi les pages déjà calées au pixel.
     *
     * @param list<array<string, mixed>> $sections
     */
    private function renderSections(array $sections): string
    {
        $morceaux = [];

        foreach ($sections as $section) {
            $titre = (string) ($section['title'] ?? '');
            $bloc = '' !== $titre ? '## '.$titre : '';

            if (isset($section['intro']) && \is_string($section['intro'])) {
                $bloc .= "\n\n".$section['intro'];
            }

            if (isset($section['paragraphs']) && \is_array($section['paragraphs'])) {
                foreach ($section['paragraphs'] as $paragraphe) {
                    if (\is_string($paragraphe)) {
                        $bloc .= "\n\n".$paragraphe;
                    }
                }
            }

            if (isset($section['items']) && \is_array($section['items'])) {
                foreach ($section['items'] as $item) {
                    if (\is_string($item)) {
                        $bloc .= "\n- ".$item;
                    }
                }
            }

            $morceaux[] = trim($bloc);
        }

        return implode("\n\n", array_filter($morceaux));
    }
}
