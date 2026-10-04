<?php

declare(strict_types=1);

namespace App\Provider\Controller\Space;

use App\Provider\Entity\ProviderProfile;
use App\Provider\Repository\ProviderProfileRepository;
use App\Provider\StaticProviderSpace;
use App\Shared\Service\AccountIdentityPresenter;
use App\User\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * Socle des écrans de l'espace professionnel (maquettes
 * docs/maquettes/profil_professionnel, 02/10) : compte en session, dossier
 * prestataire, et rendu dans le gabarit commun (sidebar pro + promo).
 */
abstract class AbstractProviderSpaceController extends AbstractController
{
    protected ProviderProfileRepository $providerProfiles;
    protected AccountIdentityPresenter $identity;
    private ?ProviderProfile $provider = null;

    #[Required]
    public function setSpaceDependencies(ProviderProfileRepository $providerProfiles, AccountIdentityPresenter $identity): void
    {
        $this->providerProfiles = $providerProfiles;
        $this->identity = $identity;
    }

    /**
     * @param array<string, mixed> $parameters
     */
    protected function renderSpace(string $template, string $active, array $parameters = [], ?Response $response = null): Response
    {
        $user = $this->currentUser();

        return $this->render($template, $parameters + [
            'user' => $this->identity->identityFor($user),
            'menu' => StaticProviderSpace::menu(),
            'active' => $active,
            'provider' => $this->currentProvider(),
        ], $response);
    }

    protected function currentUser(): User
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }

    protected function currentProvider(): ProviderProfile
    {
        if (null !== $this->provider) {
            return $this->provider;
        }

        $provider = $this->providerProfiles->findOneByUser($this->currentUser());

        if (null === $provider) {
            throw $this->createAccessDeniedException('Aucun dossier prestataire rattaché à ce compte.');
        }

        return $this->provider = $provider;
    }

    protected function csrfOk(Request $request): bool
    {
        if ($this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))) {
            return true;
        }

        $this->addFlash('error', 'Votre session a expiré, merci de réessayer.');

        return false;
    }

    /** Retour à la page d'origine (formulaires d'actions en ligne). */
    protected function back(Request $request, string $fallbackRoute): Response
    {
        $referer = (string) $request->headers->get('referer', '');
        if ('' !== $referer && str_starts_with($referer, $request->getSchemeAndHttpHost())) {
            return $this->redirect($referer);
        }

        return $this->redirectToRoute($fallbackRoute);
    }

    protected static function nullIfEmpty(mixed $value, int $max = 255): ?string
    {
        $value = trim((string) $value);

        return '' !== $value ? mb_substr($value, 0, $max) : null;
    }

    /**
     * Période « semaine / mois » à partir du paramètre `periode` (7, 30, 90 jours).
     *
     * @return array{0: \DateTimeImmutable, 1: \DateTimeImmutable, 2: int}
     */
    protected static function period(Request $request, int $default = 7): array
    {
        $days = $request->query->getInt('periode', $default);
        if (!\in_array($days, [7, 30, 90, 365], true)) {
            $days = $default;
        }
        $to = new \DateTimeImmutable('today 23:59:59');
        $from = $to->modify(sprintf('-%d days', $days - 1))->setTime(0, 0);

        return [$from, $to, $days];
    }

    /**
     * Fichier CSV (séparateur « ; », BOM UTF-8 pour Excel).
     *
     * @param list<string>                     $header
     * @param iterable<list<string|int|float>> $rows
     */
    protected static function csv(string $filename, array $header, iterable $rows): Response
    {
        $handle = fopen('php://temp', 'r+');
        \assert(false !== $handle);
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, $header, ';', '"', '');
        foreach ($rows as $row) {
            fputcsv($handle, $row, ';', '"', '');
        }
        rewind($handle);
        $content = (string) stream_get_contents($handle);
        fclose($handle);

        return new Response($content, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
        ]);
    }
}
