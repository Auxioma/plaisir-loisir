<?php

declare(strict_types=1);

namespace App\Shared\Controller;

use Symfony\Component\Form\FormInterface;

/**
 * Recopie les erreurs de validation d'un formulaire dans les messages flash.
 *
 * Extrait de `SecurityController`/`ProviderAuthController` (dupliqué à
 * l'identique dans les deux) : aucune maquette du site ne prévoit
 * d'emplacement pour un message d'erreur sous les champs, donc les erreurs
 * remontent dans le bandeau flash commun (`base.html.twig`) plutôt que via
 * `form_errors()` inline.
 */
trait FlashesFormErrorsTrait
{
    private function flashFormErrors(FormInterface $form): void
    {
        if (!$form->isSubmitted()) {
            return;
        }

        // true : on veut aussi les erreurs portées par les champs enfants,
        // pas seulement celles du formulaire lui-même.
        foreach ($form->getErrors(true) as $error) {
            $this->addFlash('error', $error->getMessage());
        }
    }
}
