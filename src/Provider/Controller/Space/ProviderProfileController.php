<?php

declare(strict_types=1);

namespace App\Provider\Controller\Space;

use App\Catalog\Entity\Service;
use App\Catalog\Enum\ServiceStatus;
use App\Messaging\Entity\Conversation;
use App\Messaging\Repository\ConversationRepository;
use App\Notification\Repository\NotificationPreferenceRepository;
use App\Provider\Entity\ProviderDocument;
use App\Provider\Enum\ProviderDocumentKind;
use App\Provider\Enum\ProviderStatus;
use App\Provider\Repository\ProviderDocumentRepository;
use App\Provider\Service\ProviderDocumentStorage;
use App\Provider\Service\ProviderSpace;
use App\Review\Entity\Review;
use App\Review\Enum\ReviewStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Ulid;

/**
 * « Mon profil » — maquette profil_infos_professionel.jpeg (02/10) : carte
 * d'identité pro, chiffres (ancienneté, avis, classement, taux de réponse),
 * onglets d'informations, badges mérités et pièces justificatives.
 */
#[IsGranted('ROLE_PROVIDER')]
final class ProviderProfileController extends AbstractProviderSpaceController
{
    private const TABS = ['infos', 'activites', 'medias', 'preferences', 'securite'];

    public function __construct(
        private readonly ProviderSpace $space,
        private readonly ConversationRepository $conversations,
        private readonly ProviderDocumentRepository $documents,
        private readonly ProviderDocumentStorage $documentStorage,
        private readonly NotificationPreferenceRepository $notificationPreferences,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route(path: ['fr' => '/pro/mon-profil', 'en' => '/en/pro/my-profile'], name: 'app_pro_my_profile')]
    public function index(Request $request): Response
    {
        $provider = $this->currentProvider();
        $me = $this->currentUser();
        $tab = (string) $request->query->get('onglet', 'infos');
        if (!\in_array($tab, self::TABS, true)) {
            $tab = 'infos';
        }

        $reviews = $this->space->reviews($provider);
        $average = $this->space->average($reviews);

        // Taux de réponse : conversations où le professionnel a répondu.
        $threads = array_filter($this->conversations->findForUser($me), static fn (Conversation $c): bool => $c->getProvider() === $provider && $c->getMessages()->count() > 0);
        $answered = array_filter($threads, static function (Conversation $c) use ($me): bool {
            foreach ($c->getMessages() as $m) {
                if ($m->getAuthor() === $me) {
                    return true;
                }
            }

            return false;
        });
        $responseRate = [] !== $threads ? (int) round(\count($answered) / \count($threads) * 100) : null;

        // Classement : part des professionnels notés moins bien que soi.
        $rank = null;
        if (null !== $average) {
            /** @var list<array{provider: string, avg: string|float}> $averages */
            $averages = $this->entityManager->createQueryBuilder()
                ->select('IDENTITY(r.provider) AS provider', 'AVG(r.rating) AS avg')
                ->from(Review::class, 'r')
                ->andWhere('r.status = :published')->setParameter('published', ReviewStatus::Published)
                ->groupBy('r.provider')
                ->getQuery()->getArrayResult();
            $better = \count(array_filter($averages, static fn (array $a): bool => (float) $a['avg'] > $average));
            $rank = max(1, (int) ceil(($better + 1) / max(1, \count($averages)) * 100));
        }

        $bookings = $this->space->bookings($provider);
        $services = array_values(array_filter($this->space->services($provider), static fn (Service $s): bool => ServiceStatus::Archived !== $s->getStatus()));
        $documents = $this->documents->findForProfile($provider);
        $verified = ProviderStatus::Verified === $provider->getStatus();

        $media = [];
        foreach ($services as $service) {
            foreach ($service->getMedia() as $m) {
                $media[] = ['path' => $m->getPath(), 'service' => $service];
            }
        }

        return $this->renderSpace('provider/space/my_profile.html.twig', 'Mon profil', [
            'tab' => $tab,
            'me' => $me,
            'average' => $average,
            'reviews_count' => \count($reviews),
            'rank' => $rank,
            'response_rate' => $responseRate,
            'services' => $services,
            'bookings_count' => \count($bookings),
            'media' => $media,
            'documents' => $documents,
            'document_kinds' => ProviderDocumentKind::cases(),
            'verified' => $verified,
            'preference' => $this->notificationPreferences->findOneByUser($me),
            'badges' => [
                ['key' => 'verified', 'label' => 'Vérifié', 'on' => $verified, 'hint' => 'Dossier professionnel validé par TrouveMoi'],
                ['key' => 'top', 'label' => 'Top noté', 'on' => null !== $average && $average >= 4.5 && \count($reviews) >= 5, 'hint' => 'Note moyenne ≥ 4,5 avec au moins 5 avis'],
                ['key' => 'fast', 'label' => 'Super réactif', 'on' => null !== $responseRate && $responseRate >= 90, 'hint' => 'Répond à 90 % des messages ou plus'],
                ['key' => 'xp', 'label' => 'Expérimenté', 'on' => \count($bookings) >= 50 || ($provider->getCreatedAt() <= new \DateTimeImmutable('-1 year')), 'hint' => '50 réservations ou un an sur TrouveMoi'],
            ],
            'promo' => ['title' => 'Développez votre activité', 'text' => 'Mettez en avant vos services et attirez plus de clients.', 'cta' => 'Découvrir nos conseils', 'href' => $this->generateUrl('app_faq')],
        ]);
    }

    #[Route(path: ['fr' => '/pro/mon-profil/documents', 'en' => '/en/pro/my-profile/documents'], name: 'app_pro_documents_upload', methods: ['POST'])]
    public function upload(Request $request): Response
    {
        if ($this->csrfOk($request)) {
            $file = $request->files->get('document');
            $kind = ProviderDocumentKind::tryFrom((string) $request->request->get('kind')) ?? ProviderDocumentKind::Other;
            if (!$file instanceof UploadedFile) {
                $this->addFlash('error', 'Choisissez un fichier (PDF ou image).');
            } else {
                try {
                    $this->documentStorage->store($this->currentProvider(), $file, $kind);
                    $this->addFlash('success', 'Document ajouté : notre équipe le vérifie sous 48 h.');
                } catch (\InvalidArgumentException $e) {
                    $this->addFlash('error', $e->getMessage());
                }
            }
        }

        return $this->redirectToRoute('app_pro_my_profile', ['_fragment' => 'documents']);
    }

    #[Route(path: ['fr' => '/pro/mon-profil/documents/{id}/supprimer', 'en' => '/en/pro/my-profile/documents/{id}/delete'], name: 'app_pro_documents_delete', methods: ['POST'])]
    public function deleteDocument(string $id, Request $request): Response
    {
        $document = Ulid::isValid($id) ? $this->documents->find(Ulid::fromString($id)) : null;
        if (!$document instanceof ProviderDocument || $document->getProviderProfile() !== $this->currentProvider()) {
            throw $this->createNotFoundException();
        }

        if ($this->csrfOk($request)) {
            $path = $this->documentStorage->pathOf($document);
            $this->entityManager->remove($document);
            $this->entityManager->flush();
            if (is_file($path)) {
                @unlink($path);
            }
            $this->addFlash('success', 'Document supprimé.');
        }

        return $this->redirectToRoute('app_pro_my_profile', ['_fragment' => 'documents']);
    }
}
