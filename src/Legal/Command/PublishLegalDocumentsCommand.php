<?php

declare(strict_types=1);

namespace App\Legal\Command;

use App\Corporate\StaticCorporate;
use App\Legal\Enum\LegalDocumentType;
use App\Legal\InitialLegalTexts;
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
 * LES TROIS TEXTES MANQUANTS SONT DÉSORMAIS PUBLIÉS (31/08).
 * Cette commande refusait jusqu'ici de publier la politique de
 * confidentialité, les conditions de vente et la politique de cookies, au
 * motif que rédiger du droit à la place du client serait au mieux inutile, au
 * pire dangereux. La conséquence était pire : le site faisait cocher
 * « j'accepte la politique de confidentialité » en renvoyant vers une page qui
 * n'existait pas, et la connexion par Facebook exige une politique publiée.
 *
 * Le porteur de projet a tranché le 31/08 : on publie une PREMIÈRE RÉDACTION,
 * conforme au RGPD, à la doctrine de la CNIL sur les traceurs et au code de la
 * consommation, qu'il corrigera ensuite depuis le back-office. Le texte est
 * dans App\Legal\InitialLegalTexts, avec ses réserves.
 *
 * C'est exactement l'usage prévu du modèle : une correction devient une
 * nouvelle version, et les acceptations déjà enregistrées continuent de
 * pointer vers celle qui était en vigueur ce jour-là.
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
                'resume' => 'Première mise en base du texte déjà publié sur le site.',
            ],
            [
                'type' => LegalDocumentType::LegalNotice,
                'titre' => 'Mentions légales',
                'contenu' => $this->renderSections(StaticCorporate::legalSections()),
                'resume' => 'Première mise en base du texte déjà publié sur le site.',
            ],
            // Les trois suivants n'avaient aucun texte : ils sont écrits, pas
            // repris. Voir les réserves en tête d'InitialLegalTexts.
            [
                'type' => LegalDocumentType::PrivacyPolicy,
                'titre' => 'Politique de confidentialité',
                'contenu' => InitialLegalTexts::privacyPolicy(),
                'resume' => 'Première rédaction. À FAIRE RELIRE PAR UN JURISTE avant usage en production : ce texte a été écrit par l\'équipe technique, il décrit fidèlement le site mais n\'a reçu aucune validation juridique.',
            ],
            [
                'type' => LegalDocumentType::TermsOfSale,
                'titre' => 'Conditions générales de vente',
                'contenu' => InitialLegalTexts::termsOfSale(),
                'resume' => 'Première rédaction. À FAIRE RELIRE PAR UN JURISTE avant usage en production : ce texte a été écrit par l\'équipe technique, il décrit fidèlement le site mais n\'a reçu aucune validation juridique.',
            ],
            [
                'type' => LegalDocumentType::CookiePolicy,
                'titre' => 'Politique de cookies',
                'contenu' => InitialLegalTexts::cookiePolicy(),
                'resume' => 'Première rédaction. À FAIRE RELIRE PAR UN JURISTE avant usage en production : ce texte a été écrit par l\'équipe technique, il décrit fidèlement le site mais n\'a reçu aucune validation juridique.',
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
                changeSummary: (string) $doc['resume'],
            );

            ++$publies;
            $io->text(sprintf('✓ %s %s (%s) publié.', $type->label(), $version, $locale));
        }

        $manquants = [];
        foreach (LegalDocumentType::cases() as $type) {
            if (null === $this->documents->findCurrent($type, $locale)) {
                $manquants[] = $type->label();
            }
        }

        if ([] !== $manquants) {
            $io->warning(sprintf('Aucun texte en vigueur pour : %s.', implode(', ', $manquants)));
        }

        $io->note(
            "La politique de confidentialité, les conditions de vente et la politique de cookies sont une PREMIÈRE RÉDACTION.\n".
            "Elles décrivent fidèlement le site et suivent le RGPD, la doctrine de la CNIL et le code de la consommation,\n".
            "mais elles n'ont reçu aucune validation juridique. Deux points restent à compléter par l'éditeur :\n".
            "  - l'identité de l'éditeur reprend celle des mentions légales, qui est fictive ;\n".
            "  - le médiateur de la consommation, obligatoire (art. L616-1), reste à désigner.\n".
            'Les corrections se font depuis le back-office : chacune devient une nouvelle version.'
        );

        $io->success(sprintf('%d document(s) publié(s).', $publies));

        return Command::SUCCESS;
    }

    /**
     * Convertit les sections de StaticCorporate en HTML.
     *
     * POURQUOI DU HTML ET NON DU TEXTE
     * Quand cette commande a été écrite, rien ne relisait le contenu : elle
     * produisait un format à tirets, pratique à écrire, que personne n'avait
     * jamais eu à afficher. Depuis le 31/08 les pages légales lisent la base,
     * et le back-office fait saisir ce texte dans un éditeur de texte riche,
     * qui produit du HTML. Deux formats en base auraient signifié deux rendus
     * à maintenir, et une page dont l'apparence dépendrait de la façon dont sa
     * version a été créée. On n'en garde donc qu'un.
     *
     * Chaque titre de niveau 2 ouvre un article : c'est la seule convention,
     * et c'est elle qui fabrique le sommaire « Sur cette page » à l'affichage.
     *
     * Le texte est échappé au passage. Il vient de StaticCorporate, donc de
     * nous, mais une esperluette dans « Plaisirs & Loisirs » suffirait à
     * produire du HTML invalide.
     *
     * @param list<array<string, mixed>> $sections
     */
    private function renderSections(array $sections): string
    {
        $morceaux = [];

        foreach ($sections as $section) {
            $titre = (string) ($section['title'] ?? '');
            $bloc = '' !== $titre ? '<h2>'.htmlspecialchars($titre, \ENT_QUOTES).'</h2>' : '';

            if (isset($section['intro']) && \is_string($section['intro'])) {
                $bloc .= $this->paragraphe($section['intro']);
            }

            if (isset($section['paragraphs']) && \is_array($section['paragraphs'])) {
                foreach ($section['paragraphs'] as $paragraphe) {
                    if (\is_string($paragraphe)) {
                        $bloc .= $this->paragraphe($paragraphe);
                    }
                }
            }

            if (isset($section['items']) && \is_array($section['items'])) {
                $puces = '';

                foreach ($section['items'] as $item) {
                    if (\is_string($item)) {
                        $puces .= '<li>'.htmlspecialchars($item, \ENT_QUOTES).'</li>';
                    }
                }

                if ('' !== $puces) {
                    $bloc .= '<ul>'.$puces.'</ul>';
                }
            }

            if ('' !== $bloc) {
                $morceaux[] = $bloc;
            }
        }

        return implode("\n", $morceaux);
    }

    private function paragraphe(string $texte): string
    {
        return '<p>'.htmlspecialchars($texte, \ENT_QUOTES).'</p>';
    }
}
