<?php

declare(strict_types=1);

namespace App\Support\Controller;

use App\Legal\Service\LegalContentRenderer;
use App\Support\Entity\FaqEntry;
use App\Support\Enum\FaqCategory;
use App\Support\Repository\FaqEntryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Centre d'aide et FAQ.
 *
 * POURQUOI CES DEUX PAGES EXISTENT
 * Le pied de page les annonce depuis l'origine, et la barre de navigation
 * institutionnelle porte une entrée « FAQ » ; les trois liens pointaient sur
 * « # ». Le contenu vient de la base : le CTO a demandé le 29/08 que les
 * textes du site soient gérés depuis la base plutôt que déployés.
 *
 * CE QUI SÉPARE LES DEUX ÉCRANS
 * Le Centre d'aide est une porte d'entrée : les rubriques, une recherche, et
 * les questions les plus consultées. La FAQ est la liste complète, dépliable,
 * groupée par rubrique. Les deux lisent la même table ; aucune ne duplique le
 * contenu de l'autre.
 */
final class SupportController extends AbstractController
{
    #[Route(path: ['fr' => '/centre-d-aide', 'en' => '/en/help-center'], name: 'app_help_center')]
    public function helpCenter(Request $request, FaqEntryRepository $faq): Response
    {
        $locale = $request->getLocale();
        $terme = trim((string) $request->query->get('q', ''));

        // Une recherche depuis le Centre d'aide n'affiche pas ses résultats
        // sur place : elle emmène sur la FAQ, qui sait déjà les présenter.
        // Deux pages qui affichent des résultats seraient deux pages à tenir.
        if ('' !== $terme) {
            return $this->redirectToRoute('app_faq', ['q' => $terme]);
        }

        $rubriques = [];

        foreach (FaqCategory::ordered() as $rubrique) {
            $rubriques[] = [
                'value' => $rubrique->value,
                'label' => $rubrique->label(),
                'description' => $rubrique->description(),
                'icon' => $rubrique->icon(),
                'count' => $faq->countPublished($rubrique, $locale),
            ];
        }

        return $this->render('support/centre_aide.html.twig', [
            'categories' => $rubriques,
            'featured' => $faq->featured($locale),
        ]);
    }

    #[Route(path: ['fr' => '/faq', 'en' => '/en/faq'], name: 'app_faq')]
    public function faq(Request $request, FaqEntryRepository $faq, LegalContentRenderer $renderer): Response
    {
        $locale = $request->getLocale();
        $terme = trim((string) $request->query->get('q', ''));
        $filtre = FaqCategory::tryFrom((string) $request->query->get('rubrique', ''));

        if ('' !== $terme) {
            $trouvees = $faq->search($terme, $locale);

            return $this->render('support/faq.html.twig', [
                'groupes' => ['' => $this->prepare($trouvees, $renderer)],
                'categories' => $this->categories(),
                'terme' => $terme,
                'filtre' => null,
                'total' => \count($trouvees),
            ]);
        }

        $groupes = [];
        $total = 0;

        foreach ($faq->publishedByCategory($locale) as $cle => $questions) {
            // Le filtre par rubrique masque les autres au lieu de refaire une
            // requête : la FAQ tient en quelques dizaines de lignes, et garder
            // un seul chemin de lecture évite deux comportements à vérifier.
            if (null !== $filtre && $filtre->value !== $cle) {
                continue;
            }

            $groupes[$cle] = $this->prepare($questions, $renderer);
            $total += \count($questions);
        }

        return $this->render('support/faq.html.twig', [
            'groupes' => $groupes,
            'categories' => $this->categories(),
            'terme' => '',
            'filtre' => $filtre?->value,
            'total' => $total,
        ]);
    }

    /**
     * Filtre les réponses avant l'affichage.
     *
     * Le filtrage a lieu ici et non dans le gabarit pour une raison simple :
     * un gabarit qui reçoit du HTML doit pouvoir l'afficher sans réfléchir. Si
     * la décision de nettoyer restait dans Twig, il suffirait d'un oubli de
     * « raw » mal placé, un jour, pour publier une faille.
     *
     * @param list<FaqEntry> $questions
     *
     * @return list<array{entry: FaqEntry, answer: string}>
     */
    private function prepare(array $questions, LegalContentRenderer $renderer): array
    {
        return array_map(
            static fn (FaqEntry $question): array => [
                'entry' => $question,
                'answer' => $renderer->clean($question->getAnswer()),
            ],
            $questions,
        );
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function categories(): array
    {
        return array_map(
            static fn (FaqCategory $rubrique): array => [
                'value' => $rubrique->value,
                'label' => $rubrique->label(),
            ],
            FaqCategory::ordered(),
        );
    }
}
