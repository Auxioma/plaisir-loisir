<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Support\Entity\FaqEntry;
use App\Support\Enum\FaqCategory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Le Centre d'aide et la FAQ.
 *
 * POURQUOI CES DEUX ÉCRANS EXISTENT
 * Le pied de page les annonçait depuis l'origine et la barre de navigation
 * institutionnelle porte une entrée « FAQ » : les trois liens pointaient sur
 * « # ». Le contenu vient désormais de la base, comme les textes juridiques,
 * à la demande du CTO du 29/08.
 *
 * CE QUE CES TESTS VÉRIFIENT VRAIMENT
 * Qu'une question saisie en base APPARAÎT sur le site, qu'elle disparaît quand
 * on la dépublie, et que les états vides — rubrique sans question, recherche
 * sans résultat — expliquent la situation au lieu de montrer une page nue.
 * C'est ce dernier point qui compte : en production, la FAQ démarre vide.
 */
final class FaqPagesTest extends WebTestCase
{
    public function testTheHelpCenterAndTheFaqAnswer(): void
    {
        $client = static::createClient();

        foreach (['/centre-d-aide', '/faq', '/en/help-center', '/en/faq'] as $url) {
            $client->request('GET', $url);
            self::assertSame(200, $client->getResponse()->getStatusCode(), sprintf('%s ne répond pas.', $url));
        }
    }

    /**
     * LE TEST QUI COMPTE : la saisie atteint le site.
     */
    public function testAQuestionEnteredInTheDatabaseAppearsOnTheFaq(): void
    {
        $client = static::createClient();
        $question = $this->addQuestion('Question temoin '.uniqid(), FaqCategory::Payment);

        $crawler = $client->request('GET', '/faq');

        // Il y a UN accordéon par rubrique : `->text()` ne lirait que le
        // premier, et la question de ce test est dans un autre. On rassemble
        // donc le texte de tous les accordéons de la page.
        $accordeons = implode(
            ' ',
            $crawler->filter('.sp-accordion')->each(static fn ($noeud): string => $noeud->text('')),
        );

        self::assertStringContainsString(
            $question->getQuestion(),
            $accordeons,
            "La question saisie en base n'apparaît pas sur /faq.",
        );
    }

    public function testAnUnpublishedQuestionStaysHidden(): void
    {
        $client = static::createClient();
        $question = $this->addQuestion('Brouillon '.uniqid(), FaqCategory::Payment, publiee: false);

        $client->request('GET', '/faq');

        self::assertStringNotContainsString(
            $question->getQuestion(),
            (string) $client->getResponse()->getContent(),
            'Une question dépubliée reste visible : la case « Publiée » ne sert à rien.',
        );
    }

    public function testTheTopicFilterKeepsOnlyItsOwnQuestions(): void
    {
        $client = static::createClient();
        $paiement = $this->addQuestion('Paiement temoin '.uniqid(), FaqCategory::Payment);
        $compte = $this->addQuestion('Compte temoin '.uniqid(), FaqCategory::Account);

        $client->request('GET', '/faq?rubrique=payment');
        $page = (string) $client->getResponse()->getContent();

        self::assertStringContainsString($paiement->getQuestion(), $page);
        self::assertStringNotContainsString($compte->getQuestion(), $page, 'Le filtre par rubrique laisse passer les autres rubriques.');
    }

    public function testAnUnknownTopicShowsEverythingRatherThanFailing(): void
    {
        $client = static::createClient();
        $this->addQuestion('Question quelconque '.uniqid(), FaqCategory::Booking);

        $client->request('GET', '/faq?rubrique=rubrique-qui-nexiste-pas');

        // Une rubrique inconnue vient forcément d'une adresse tapée à la main
        // ou d'un vieux lien : mieux vaut la FAQ complète qu'une erreur.
        self::assertSame(200, $client->getResponse()->getStatusCode());
        self::assertStringContainsString('sp-accordion', (string) $client->getResponse()->getContent());
    }

    public function testSearchFindsAQuestionByItsAnswer(): void
    {
        $client = static::createClient();
        $motRare = 'anticonstitutionnellement'.uniqid();
        $this->addQuestion('Question a reponse cherchable '.uniqid(), FaqCategory::Booking, reponse: '<p>Reponse contenant '.$motRare.'.</p>');

        $crawler = $client->request('GET', '/faq?q='.$motRare);

        self::assertGreaterThan(
            0,
            $crawler->filter('.sp-item')->count(),
            'La recherche ne trouve pas un mot présent dans la réponse : elle ne cherche que dans les intitulés.',
        );
    }

    public function testASearchWithoutResultExplainsItself(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/faq?q=zzzzzz'.uniqid());

        self::assertSame(200, $client->getResponse()->getStatusCode());
        self::assertGreaterThan(0, $crawler->filter('.sp-empty')->count(), 'Une recherche sans résultat affiche une page nue.');
        self::assertGreaterThan(0, $crawler->filter('.sp-empty a[href*="contact"]')->count(), 'Aucune porte de sortie proposée.');
    }

    /**
     * Une rubrique vide reste visible, mais éteinte.
     *
     * La faire disparaître ferait une grille à trous sur le Centre d'aide, et
     * laisserait croire que la rubrique n'existe pas alors qu'elle attend
     * seulement son contenu.
     */
    public function testAnEmptyTopicIsShownAsComingSoonRatherThanHidden(): void
    {
        $client = static::createClient();
        $this->emptyTopic(FaqCategory::Providers);

        $crawler = $client->request('GET', '/centre-d-aide');

        self::assertGreaterThan(
            0,
            $crawler->filter('.sp-card--empty')->count(),
            'Une rubrique sans question disparaît du Centre d\'aide au lieu de s\'annoncer.',
        );
    }

    /**
     * Une rubrique remplie doit, elle, conduire à ses questions.
     */
    public function testAFilledTopicLinksToItsQuestions(): void
    {
        $client = static::createClient();
        $this->addQuestion('Question de rubrique '.uniqid(), FaqCategory::Gifts);

        $crawler = $client->request('GET', '/centre-d-aide');

        self::assertGreaterThan(
            0,
            $crawler->filter('a.sp-card[href*="rubrique=gifts"]')->count(),
            'La carte d\'une rubrique remplie ne mène pas à ses questions.',
        );
    }

    /**
     * Une réponse contenant un script ne doit pas ressortir dans la page.
     */
    public function testAScriptInAnAnswerNeverReachesThePage(): void
    {
        $client = static::createClient();
        $this->addQuestion(
            'Question piegee '.uniqid(),
            FaqCategory::Booking,
            reponse: '<p>Reponse licite.</p><script>alert("faq")</script>',
        );

        $client->request('GET', '/faq');

        self::assertStringNotContainsString('alert("faq")', (string) $client->getResponse()->getContent(), 'Un script stocké dans une réponse est ressorti dans la page.');
    }

    /**
     * Les trois liens qui pointaient sur « # ».
     */
    public function testTheHelpLinksNoLongerPointNowhere(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/');
        $piedDePage = $crawler->filter('.pl-footer a')->each(static fn ($n): string => (string) $n->attr('href'));
        self::assertContains('/centre-d-aide', $piedDePage, "Le pied de page ne mène pas au Centre d'aide.");
        self::assertContains('/faq', $piedDePage, 'Le pied de page ne mène pas à la FAQ.');

        // L'entrée « FAQ » de la barre institutionnelle, qui était le
        // troisième lien mort.
        $crawler = $client->request('GET', '/a-propos');
        self::assertGreaterThan(
            0,
            $crawler->filter('.pl-nav__link[href="/faq"]')->count(),
            "L'entrée FAQ de la barre de navigation institutionnelle ne mène toujours nulle part.",
        );
    }

    private function addQuestion(
        string $intitule,
        FaqCategory $rubrique,
        bool $publiee = true,
        string $reponse = '<p>Une reponse.</p>',
    ): FaqEntry {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $question = (new FaqEntry())
            ->setCategory($rubrique)
            ->setLocale('fr')
            ->setQuestion($intitule)
            ->setAnswer($reponse)
            ->setPublished($publiee);

        $entityManager->persist($question);
        $entityManager->flush();

        return $question;
    }

    /**
     * La base de test n'est pas remise à zéro entre deux exécutions : pour
     * observer une rubrique vide, il faut la vider.
     */
    private function emptyTopic(FaqCategory $rubrique): void
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        foreach ($entityManager->getRepository(FaqEntry::class)->findBy(['category' => $rubrique]) as $question) {
            $entityManager->remove($question);
        }

        $entityManager->flush();
    }
}
