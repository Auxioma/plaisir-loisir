<?php

declare(strict_types=1);

namespace App\I18n\Translation;

use App\I18n\Repository\TranslationRepository;
use Symfony\Component\Translation\Loader\LoaderInterface;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Yaml\Yaml;

/**
 * Charge le catalogue de traduction depuis la table `translation` (demande
 * CTO : traductions administrables par le client sans redéploiement).
 *
 * Le translator est branché via un fichier témoin `translations/messages.en.db`
 * (extension = alias de ce loader). ⚠️ Le catalogue compilé est mis en cache
 * par Symfony : après une modification en base, exécuter `cache:clear`
 * (le futur écran d'admin devra invalider ce cache à chaque sauvegarde).
 *
 * Filet de sécurité : si la base est indisponible ou vide (poste fraîchement
 * cloné, CI), on retombe sur la graine config/i18n/messages.{locale}.yaml
 * pour que le site reste traduit. ⚠️ La graine vit hors de translations/ :
 * ce répertoire est scanné récursivement par le framework et un YAML qui y
 * traînerait écraserait les valeurs administrées en base (dernier chargé
 * gagne).
 */
final class DatabaseTranslationLoader implements LoaderInterface
{
    public function __construct(
        private readonly TranslationRepository $repository,
        private readonly string $projectDir,
    ) {
    }

    public function load(mixed $resource, string $locale, string $domain = 'messages'): MessageCatalogue
    {
        try {
            $messages = $this->repository->findCatalogue($locale, $domain);
        } catch (\Throwable) {
            $messages = [];
        }

        if ([] === $messages) {
            $seed = \sprintf('%s/config/i18n/%s.%s.yaml', $this->projectDir, $domain, $locale);
            if (is_file($seed)) {
                /** @var array<string, string> $messages */
                $messages = Yaml::parseFile($seed) ?? [];
            }
        }

        $catalogue = new MessageCatalogue($locale);
        $catalogue->add($messages, $domain);

        return $catalogue;
    }
}
