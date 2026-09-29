<?php

declare(strict_types=1);

namespace App\Admin\EventSubscriber;

use Doctrine\DBAL\Exception\ConstraintViolationException;
use Doctrine\DBAL\Exception\DriverException;
use EasyCorp\Bundle\EasyAdminBundle\Exception\EntityNotFoundException;
use EasyCorp\Bundle\EasyAdminBundle\Exception\EntityRemoveException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Exception\ExceptionInterface as RoutingException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Back-office : une erreur ramène à la liste, jamais à une page d'erreur.
 *
 * Après le déploiement, cliquer sur une activité supprimée entre-temps (lien
 * gardé en favori, onglet resté ouvert, double clic sur « Supprimer »)
 * affichait une page d'erreur sèche. L'administrateur n'a rien à en faire :
 * il veut retrouver sa liste. On le renvoie donc à la liste de la même
 * rubrique (admin_service_edit → admin_service_index), avec un message qui
 * dit ce qui s'est passé. Si c'est la liste elle-même qui échoue, on remonte
 * au tableau de bord ; si c'est le tableau de bord, on laisse l'erreur
 * s'afficher, faute de lieu plus sûr où aller (et pour ne pas boucler).
 *
 * En développement (kernel.debug), seules les erreurs « attendues » (fiche
 * introuvable, accès refusé) sont redirigées : une vraie panne doit rester
 * visible avec sa trace, sinon on ne la corrige jamais.
 *
 * Priorité -32 : après le pare-feu (1), qui transforme un visiteur anonyme en
 * redirection vers la connexion, et avant la page d'erreur d'EasyAdmin (-64).
 * L'erreur est déjà journalisée par Symfony (priorité 0) : on ne perd rien.
 */
final class AdminErrorRedirectSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly UrlGeneratorInterface $urls,
        #[Autowire('%kernel.debug%')]
        private readonly bool $debug,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => [['onKernelException', -32]],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest() || !$this->isAdminRequest($request) || $request->isXmlHttpRequest()) {
            return;
        }

        $exception = $event->getThrowable();
        $status = $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500;

        // En développement, une vraie panne doit rester visible avec sa trace.
        // Les refus « attendus » (fiche introuvable, accès refusé, suppression
        // bloquée, valeur refusée par la base) sont redirigés partout.
        if ($this->debug && !\in_array($status, [403, 404, 409], true) && !$this->isRefusedByDatabase($exception)) {
            return;
        }

        $target = $this->fallbackUrl((string) $request->attributes->get('_route', ''));
        if (null === $target) {
            return;
        }

        $session = $request->hasSession() ? $request->getSession() : null;
        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add('danger', $this->message($status, $exception));
        }

        $this->logger?->info('Erreur back-office redirigée vers {target}.', [
            'target' => $target,
            'status' => $status,
            'exception' => $exception,
        ]);

        // 303 : après un POST (enregistrement, suppression), le navigateur
        // doit revenir en GET, et non rejouer l'envoi du formulaire.
        $event->setResponse(new RedirectResponse($target, RedirectResponse::HTTP_SEE_OTHER));
    }

    /**
     * La base a refusé l'écriture (doublon, lien manquant, valeur trop
     * longue…) : c'est la saisie qui est en cause, pas le code.
     */
    private function isRefusedByDatabase(\Throwable $exception): bool
    {
        for ($e = $exception; null !== $e; $e = $e->getPrevious()) {
            if ($e instanceof ConstraintViolationException || ($e instanceof DriverException && \in_array($e->getSQLState(), ['22001', '22003', '23502', '23503', '23505'], true))) {
                return true;
            }
        }

        return false;
    }

    private function isAdminRequest(Request $request): bool
    {
        $path = $request->getPathInfo();

        return '/admin' === $path || str_starts_with($path, '/admin/');
    }

    /**
     * Liste de la même rubrique, sinon tableau de bord, sinon rien.
     */
    private function fallbackUrl(string $route): ?string
    {
        if ('admin' === $route) {
            return null;
        }

        // Routes EasyAdmin : admin_<rubrique>_<action>, où rubrique et action
        // peuvent contenir des « _ » (admin_contact_message_mark_handled). On
        // retire les segments un à un jusqu'à trouver la liste de la rubrique.
        if (!str_ends_with($route, '_index')) {
            $prefix = $route;
            while (false !== ($cut = strrpos($prefix, '_')) && $cut > \strlen('admin')) {
                $prefix = substr($prefix, 0, $cut);
                try {
                    return $this->urls->generate($prefix.'_index');
                } catch (RoutingException) {
                    // Pas encore la rubrique : on retire un segment de plus.
                }
            }
        }

        try {
            return $this->urls->generate('admin');
        } catch (RoutingException) {
            return null;
        }
    }

    private function message(int $status, \Throwable $exception): string
    {
        return match (true) {
            $exception instanceof EntityNotFoundException, 404 === $status => 'Cet élément n\'existe pas ou a été supprimé. Vous avez été renvoyé à la liste.',
            403 === $status => 'Vous n\'avez pas les droits pour effectuer cette action.',
            $exception instanceof EntityRemoveException, 409 === $status => 'Suppression impossible : cet élément est encore utilisé ailleurs (réservations, avis, activités…). Retirez d\'abord ces liens, ou dépubliez-le plutôt que de le supprimer.',
            $this->isRefusedByDatabase($exception) => 'Enregistrement refusé : une valeur saisie est en double, trop longue ou manquante. Vérifiez le formulaire et réessayez.',
            default => 'Une erreur est survenue, l\'action n\'a pas pu aboutir. Réessayez ; si le problème persiste, contactez le support technique.',
        };
    }
}
