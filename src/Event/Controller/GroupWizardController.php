<?php

declare(strict_types=1);

namespace App\Event\Controller;

use App\Event\StaticGroupWizard;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Flow « Créer un groupe » (spec Partie 2) : wizard centré une colonne en
 * 4 étapes (emplacement, types d'événements, nom, description) + succès.
 * Même principe que le wizard événement : un layout décliné par l'URL.
 */
final class GroupWizardController extends AbstractController
{
    #[Route('/evenements/groupes/creer/succes', name: 'app_group_create_success')]
    public function success(): Response
    {
        return $this->render('event/group/succes.html.twig');
    }

    #[Route('/evenements/groupes/creer/{etape}', name: 'app_group_create', requirements: ['etape' => '[1-4]'], defaults: ['etape' => 1])]
    public function create(int $etape): Response
    {
        return $this->render('event/group/creer.html.twig', [
            'step' => $etape,
            'advice' => StaticGroupWizard::advice($etape),
            'tags' => StaticGroupWizard::tags(),
            'cities' => StaticGroupWizard::cities(),
        ]);
    }
}
