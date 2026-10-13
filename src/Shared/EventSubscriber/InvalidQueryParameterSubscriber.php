<?php

declare(strict_types=1);

namespace App\Shared\EventSubscriber;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Recherches et filtres (13/10, retour client : « une erreur apparaît lors
 * de certaines recherches »). Un paramètre d'adresse mal formé — lettres
 * dans un champ numérique, valeur unique là où une liste est attendue,
 * lien tronqué ou retouché à la main — faisait afficher une page d'erreur.
 * Sur une page consultée (GET), on revient plutôt à la même page sans ses
 * paramètres : la personne retrouve la liste au lieu d'une impasse.
 */
#[AsEventListener]
final class InvalidQueryParameterSubscriber
{
    public function __invoke(ExceptionEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest() || !$request->isMethod('GET') || '' === (string) $request->getQueryString()) {
            return;
        }

        for ($e = $event->getThrowable(); null !== $e; $e = $e->getPrevious()) {
            if ($e instanceof BadRequestException || ($e instanceof BadRequestHttpException && $e->getPrevious() instanceof \UnexpectedValueException)) {
                $event->setResponse(new RedirectResponse($request->getBaseUrl().$request->getPathInfo()));

                return;
            }
        }
    }
}
