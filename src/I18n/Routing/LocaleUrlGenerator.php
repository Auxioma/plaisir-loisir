<?php

declare(strict_types=1);

namespace App\I18n\Routing;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * Traduit l'adresse de la page courante d'une langue vers l'autre.
 *
 * Depuis que la langue vit dans l'URL (et non plus en session), changer de
 * langue n'est plus un reglage a memoriser : c'est un deplacement vers une
 * AUTRE page. Ce service calcule cette page, et sert aussi bien au selecteur
 * de langue qu'aux balises <link rel="alternate" hreflang> attendues par les
 * moteurs de recherche.
 */
final class LocaleUrlGenerator
{
    /**
     * Les langues du site, dans l'ordre d'affichage. Le francais est la langue
     * de reference : ses adresses n'ont pas de prefixe.
     */
    public const LOCALES = ['fr', 'en'];

    /**
     * L'onglet d'une fiche de groupe apparait dans l'URL. Sa valeur francaise
     * reste l'identifiant interne (les gabarits comparent `tab == t.key`) ;
     * seul le segment affiche change de langue.
     */
    private const TAB_SEGMENTS = [
        'apropos' => 'about',
        'evenements' => 'events',
        'membres' => 'members',
        'photos' => 'photos',
        'discussions' => 'discussions',
    ];

    public function __construct(private readonly RouterInterface $router)
    {
    }

    /**
     * Segment d'URL a employer pour un onglet de groupe dans la langue donnee.
     */
    public static function tabSegment(string $key, string $locale): string
    {
        if ('en' !== $locale) {
            return $key;
        }

        return self::TAB_SEGMENTS[$key] ?? $key;
    }

    /**
     * Identifiant interne correspondant a un segment d'onglet recu dans l'URL,
     * quelle que soit la langue employee.
     */
    public static function tabKey(string $segment): string
    {
        $key = array_search($segment, self::TAB_SEGMENTS, true);

        return \is_string($key) ? $key : $segment;
    }

    /**
     * Adresse de la page courante dans la langue demandee.
     *
     * Une page sans route identifiable (page d'erreur) ou une route technique
     * sans variante traduite renvoie vers l'accueil de la langue demandee :
     * mieux vaut une page d'accueil dans la bonne langue qu'un lien mort.
     */
    public function switchTo(Request $request, string $locale, bool $absolute = false, bool $withQuery = true): string
    {
        $type = $absolute ? UrlGeneratorInterface::ABSOLUTE_URL : UrlGeneratorInterface::ABSOLUTE_PATH;
        $route = $request->attributes->get('_route');

        if (!\is_string($route) || '' === $route || !$this->isTranslated($route, $locale)) {
            return $this->router->generate('app_home', ['_locale' => $locale], $type);
        }

        $params = $request->attributes->get('_route_params');
        $params = \is_array($params) ? $params : [];
        if (isset($params['onglet']) && \is_string($params['onglet'])) {
            $params['onglet'] = self::tabSegment(self::tabKey($params['onglet']), $locale);
        }
        $params['_locale'] = $locale;

        $url = $this->router->generate($route, $params, $type);

        // Les filtres et la recherche vivent dans la chaine de requete : le
        // selecteur de langue la conserve, sinon changer de langue
        // reinitialiserait la page. Les balises SEO, elles, designent la page
        // nue : sans cela chaque combinaison de filtres deviendrait une page
        // a indexer, donc du contenu duplique.
        $query = $withQuery ? $request->getQueryString() : null;

        return null !== $query && '' !== $query ? $url.'?'.$query : $url;
    }

    /**
     * Adresses absolues de la page courante dans toutes les langues, pour les
     * balises hreflang.
     *
     * @return array<string, string>
     */
    public function alternates(Request $request): array
    {
        $alternates = [];
        foreach (self::LOCALES as $locale) {
            $alternates[$locale] = $this->switchTo($request, $locale, true, false);
        }

        return $alternates;
    }

    /**
     * Une route possede-t-elle une variante dans cette langue ? Les routes
     * techniques (pare-feu, retours OAuth, webhook Stripe) n'en ont pas.
     */
    private function isTranslated(string $route, string $locale): bool
    {
        return null !== $this->router->getRouteCollection()->get($route.'.'.$locale);
    }
}
