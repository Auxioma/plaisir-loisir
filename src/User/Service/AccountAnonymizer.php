<?php

declare(strict_types=1);

namespace App\User\Service;

use App\User\Entity\User;
use App\User\Enum\UserStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Efface les données personnelles d'un compte sans supprimer la ligne.
 *
 * POURQUOI ON N'EFFACE PAS LA LIGNE
 * Deux obligations s'opposent, et il faut les concilier plutôt que choisir.
 * Le RGPD donne un droit à l'effacement ; l'article L123-22 du code de commerce
 * impose de conserver dix ans les pièces comptables, et une réservation payée
 * en est une. Supprimer la ligne casserait de toute façon la base : les
 * réservations, les paiements et les acceptations des conditions générales la
 * référencent, et ces clés étrangères sont en RESTRICT.
 *
 * La conciliation admise est celle-ci : on retire tout ce qui identifie la
 * personne, et on garde la coquille pour que l'historique comptable reste
 * cohérent. La réservation n° 142 existe toujours, elle n'appartient plus à
 * personne de nommable.
 *
 * CE QUI EST EFFACÉ, ET POURQUOI CHAQUE CHAMP
 *  - l'adresse électronique, remplacée par une adresse technique unique : la
 *    colonne est unique et non nulle, on ne peut pas simplement la vider ;
 *  - le nom et le prénom, qui apparaissent sous les avis publiés ;
 *  - le téléphone et les adresses postales, qui n'ont plus d'usage ;
 *  - le mot de passe, remplacé par une valeur aléatoire que personne ne
 *    connaît — y compris nous.
 *
 * L'opération est IRRÉVERSIBLE et l'écran d'administration le dit avant de la
 * lancer. C'est le propre d'un effacement : un effacement réversible n'en est
 * pas un.
 */
final class AccountAnonymizer
{
    /**
     * Domaine réservé aux exemples par la RFC 2606 : aucune de ces adresses ne
     * peut exister ni recevoir de courrier, même par accident.
     */
    private const DOMAINE = '@anonyme.invalid';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $hasher,
    ) {
    }

    public function anonymize(User $user): void
    {
        if ($user->isDeleted()) {
            return;
        }

        $reference = strtolower((string) $user->getId());

        $user->setEmail($reference.self::DOMAINE);
        $user->setFirstName('Compte');
        $user->setLastName('supprimé');
        $user->setPhone(null);
        $user->setStatus(UserStatus::Suspended);

        // Un mot de passe aléatoire, jamais communiqué : le compte devient
        // inaccessible même si le contrôle d'état venait à sauter un jour.
        $user->setPassword($this->hasher->hashPassword($user, bin2hex(random_bytes(32))));

        // Une adresse postale identifie autant qu'un nom.
        foreach ($user->getAddresses()->toArray() as $adresse) {
            $user->removeAddress($adresse);
            $this->entityManager->remove($adresse);
        }

        // La date de suppression est ce que lit le contrôle d'état : sans
        // elle, le compte resterait connectable.
        $user->softDelete();

        $this->entityManager->flush();
    }
}
