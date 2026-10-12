<?php

declare(strict_types=1);

namespace App\Shared\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Données structurées schema.org (12/10, SEO) : {{ json_ld({...}) }} rend une
 * balise <script type="application/ld+json">.
 *
 * JSON_HEX_TAG / HEX_AMP / HEX_APOS / HEX_QUOT : un titre ou un avis saisi
 * par un utilisateur et contenant « </script> » ne peut pas refermer la
 * balise. Les valeurs nulles ou vides sont retirées : schema.org préfère une
 * propriété absente à une propriété vide.
 */
final class StructuredDataExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [new TwigFunction('json_ld', $this->jsonLd(...), ['is_safe' => ['html']])];
    }

    /** @param array<string, mixed> $data */
    public function jsonLd(array $data): string
    {
        $json = json_encode(['@context' => 'https://schema.org'] + self::clean($data), \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_HEX_TAG | \JSON_HEX_AMP | \JSON_HEX_APOS | \JSON_HEX_QUOT | \JSON_THROW_ON_ERROR);

        return '<script type="application/ld+json">'.$json.'</script>';
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return array<array-key, mixed>
     */
    private static function clean(array $data): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            if (\is_array($value)) {
                $value = self::clean($value);
            } elseif (\is_string($value)) {
                // Texte venu d'un rendu HTML (réponse de FAQ…) : entités décodées,
                // espaces normalisés. Le JSON reste sûr (JSON_HEX_*).
                $value = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode($value, \ENT_QUOTES | \ENT_HTML5, 'UTF-8')));
            }
            if (null === $value || '' === $value || [] === $value) {
                continue;
            }
            $out[$key] = $value;
        }

        return array_is_list($data) ? array_values($out) : $out;
    }
}
