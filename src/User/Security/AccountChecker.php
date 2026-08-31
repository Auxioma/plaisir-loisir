<?php

declare(strict_types=1);

namespace App\User\Security;

use App\User\Entity\User;
use App\User\Enum\UserStatus;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Refuse la connexion aux comptes suspendus et supprimés.
 *
 * POURQUOI CETTE CLASSE EXISTE
 * L'entité User portait un statut (actif / en attente / suspendu) et une date
 * de suppression douce depuis l'origine. Ni l'un ni l'autre n'était vérifié à
 * la connexion : le fournisseur d'utilisateurs de Symfony cherche simplement
 * l'adresse e-mail et rend la ligne trouvée, quelle que soit sa colonne
 * `status` ou `deleted_at`.
 *
 * Autrement dit, suspendre un compte ne suspendait rien, et un compte
 * « supprimé » se reconnectait normalement. Le défaut était invisible tant
 * qu'aucun écran ne permettait de suspendre — c'est-à-dire jusqu'au
 * back-office du 31/08. Ajouter le bouton sans cette classe aurait donné à
 * Loïc une commande qui ne fait rien, ce qui est pire qu'une commande absente.
 *
 * LE CAS « EN ATTENTE » EST VOLONTAIREMENT LAISSÉ PASSER
 * C'est le statut par défaut de l'entité, mais l'inscription active
 * immédiatement (RegistrationService) : personne n'arrive donc « en attente »
 * par le parcours normal. Le refuser bloquerait les comptes créés autrement —
 * fixtures, back-office, commande d'administration — sans rien protéger. Le
 * jour où une confirmation d'adresse existera, ce sera ici, en une ligne.
 */
final class AccountChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        // Le message reste le même dans les deux cas, et volontairement vague :
        // dire « ce compte a été supprimé » à qui tente une adresse au hasard
        // lui apprendrait qu'elle existe.
        if ($user->isDeleted()) {
            throw new CustomUserMessageAccountStatusException('Ce compte n\'est plus accessible. Contactez-nous si vous pensez qu\'il s\'agit d\'une erreur.');
        }

        if (UserStatus::Suspended === $user->getStatus()) {
            throw new CustomUserMessageAccountStatusException('Ce compte n\'est plus accessible. Contactez-nous si vous pensez qu\'il s\'agit d\'une erreur.');
        }
    }

    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
        // Rien à vérifier après le mot de passe : l'état du compte ne dépend
        // pas de sa validité.
    }
}
