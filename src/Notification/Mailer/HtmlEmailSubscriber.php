<?php

declare(strict_types=1);

namespace App\Notification\Mailer;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mime\Email;
use Twig\Environment;

/**
 * Habille en HTML tout e-mail parti en texte seul (12/10, retour client : « les
 * e-mails ne sont que de simples phrases »).
 *
 * Les services de la plateforme continuent d'écrire un texte simple ; ce texte
 * est gardé tel quel en alternative (text/plain), et une version HTML aux
 * couleurs de TrouveMoi est construite à partir de lui : paragraphes, liens
 * cliquables, code de vérification mis en valeur, « libellé : lien » rendu en
 * bouton. Un message multipart (texte + HTML) est aussi mieux reçu par les
 * filtres anti-spam qu'un texte brut.
 */
#[AsEventListener(event: MessageEvent::class)]
final class HtmlEmailSubscriber
{
    public function __construct(
        private readonly Environment $twig,
    ) {
    }

    public function __invoke(MessageEvent $event): void
    {
        $message = $event->getMessage();
        if (!$message instanceof Email || null !== $message->getHtmlBody() || null === $message->getTextBody()) {
            return;
        }

        $text = str_replace("\r\n", "\n", (string) $message->getTextBody());
        $message->html($this->twig->render('emails/layout.html.twig', [
            'subject' => (string) $message->getSubject(),
            'blocks' => self::blocks($text),
        ]));
    }

    /**
     * Découpe le texte en blocs typés pour le gabarit.
     *
     * @return list<array{type: string, text?: string, html?: string, label?: string, url?: string, code?: string}>
     */
    public static function blocks(string $text): array
    {
        $blocks = [];
        foreach (preg_split('/\n\s*\n/', trim($text)) ?: [] as $paragraph) {
            $paragraph = trim($paragraph);
            if ('' === $paragraph) {
                continue;
            }
            // « Votre code de vérification est : AB12CD34 » / « Code : TM-XXXX »
            if (preg_match('/^(.*\bcode\b[^:\n]*:)\s*([A-Z0-9][A-Z0-9-]{3,})\s*$/iu', $paragraph, $m)) {
                $blocks[] = ['type' => 'code', 'text' => trim($m[1], " :\n"), 'code' => $m[2]];
                continue;
            }
            // « Voir l'activité et répondre : https://… » -> bouton
            if (preg_match('/^([^\n]{2,90}?)\s*:\s*(https?:\/\/\S+)$/u', $paragraph, $m)) {
                $blocks[] = ['type' => 'button', 'label' => self::buttonLabel($m[1]), 'url' => $m[2]];
                continue;
            }
            // Signature de fin.
            if (preg_match('/^(L[’\']équipe TrouveMoi|À bientôt sur TrouveMoi)/u', $paragraph)) {
                $blocks[] = ['type' => 'signature', 'html' => self::html($paragraph)];
                continue;
            }
            $blocks[] = ['type' => 'paragraph', 'html' => self::html($paragraph)];
        }

        return $blocks;
    }

    /** Texte échappé, liens cliquables, retours à la ligne gardés. */
    private static function html(string $text): string
    {
        // Lignes coupées au milieu d'une phrase (texte justifié à ~75 colonnes
        // dans le code) : recollées. Les retours voulus (listes, « Code : … »)
        // restent, car la ligne suivante n'y commence pas par une minuscule.
        $text = preg_replace('/(?<=[\p{Ll},;’\'])\n(?=\p{Ll})/u', ' ', $text) ?? $text;
        $escaped = htmlspecialchars($text, \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
        $linked = preg_replace('/https?:\/\/[^\s<]+/u', '<a href="$0" style="color:#3b19e6;word-break:break-all;">$0</a>', $escaped) ?? $escaped;

        return nl2br($linked, false);
    }

    private static function buttonLabel(string $lead): string
    {
        $lead = trim(preg_replace('/\s+(ici|sur)$/u', '', trim($lead)) ?? $lead);

        return mb_strlen($lead) > 40 ? 'Ouvrir sur TrouveMoi' : $lead;
    }
}
