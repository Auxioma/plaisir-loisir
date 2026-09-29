<?php

declare(strict_types=1);

namespace App\Shared\Twig;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Exception\ExceptionInterface as RoutingException;
use Symfony\Component\Routing\RouterInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Lien « où aller maintenant » des pages d'erreur du site public.
 *
 * Une activité supprimée (/activites/mon-activite) doit ramener à la liste
 * des activités, un groupe disparu à la liste des groupes, etc. Plutôt
 * qu'une table à tenir à jour à chaque nouvelle page, on remonte l'adresse
 * demandée segment par segment jusqu'à la première page existante sans
 * paramètre : /activites/xyz → /activites, /compte/demandes/xyz →
 * /compte/demandes, /evenements/detail/xyz → /evenements.
 *
 * On n'y REDIRIGE pas automatiquement : une adresse morte doit répondre 404
 * pour que les moteurs de recherche l'oublient. Une redirection vers la liste
 * serait vue comme un « soft 404 » et pénaliserait le référencement. La page
 * d'erreur propose donc ce lien en bouton principal.
 */
final class ErrorPageExtension extends AbstractExtension
{
    /**
     * Libellé du bouton selon la page trouvée ; à défaut, libellé générique.
     */
    private const LABELS = [
        'app_activities' => 'Voir toutes les activités',
        'app_destinations' => 'Voir toutes les destinations',
        'app_private_activities' => 'Voir les activités entre particuliers',
    ];

    public function __construct(
        private readonly RouterInterface $router,
        private readonly RequestStack $requests,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('error_fallback', $this->fallback(...)),
        ];
    }

    /**
     * @return array{url: string, label: string}|null
     */
    public function fallback(): ?array
    {
        $request = $this->requests->getMainRequest();
        if (null === $request) {
            return null;
        }

        $segments = array_values(array_filter(explode('/', $request->getPathInfo()), static fn (string $s): bool => '' !== $s));

        // La correspondance d'adresse se fait en GET, quelle que soit la
        // méthode de la requête en erreur : c'est ce que suivra le lien.
        $context = $this->router->getContext();
        $method = $context->getMethod();
        $context->setMethod('GET');

        try {
            // On s'arrête avant la racine (/ ou /en) : l'accueil a déjà son
            // propre bouton sur la page d'erreur.
            while (\count($segments) > 1) {
                array_pop($segments);
                $path = '/'.implode('/', $segments);
                if ('/en' === $path) {
                    break;
                }

                try {
                    $match = $this->router->match($path);
                } catch (RoutingException) {
                    continue;
                }

                // Seulement la langue comme paramètre : une page à slug
                // (/activites/{slug}) pourrait elle-même ne pas exister.
                $variables = array_diff(array_keys($match), ['_route', '_controller', '_locale', '_canonical_route']);
                if ([] !== array_filter($variables, static fn (string $key): bool => !str_starts_with($key, '_'))) {
                    continue;
                }

                $route = (string) ($match['_canonical_route'] ?? $match['_route'] ?? '');

                return [
                    'url' => $request->getBaseUrl().$path,
                    'label' => self::LABELS[preg_replace('/\.(fr|en)$/', '', $route)] ?? 'Revenir à la rubrique',
                ];
            }
        } finally {
            $context->setMethod($method);
        }

        return null;
    }
}
