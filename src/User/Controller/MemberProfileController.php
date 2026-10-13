<?php

declare(strict_types=1);

namespace App\User\Controller;

use App\PrivateActivity\Entity\Participation;
use App\PrivateActivity\Entity\Photo;
use App\PrivateActivity\Entity\PrivateActivity;
use App\PrivateActivity\Enum\ParticipationStatus;
use App\PrivateActivity\Repository\MemberReviewRepository;
use App\PrivateActivity\Repository\ParticipationRepository;
use App\PrivateActivity\Repository\PrivateActivityRepository;
use App\PrivateActivity\Security\PrivateActivityVoter;
use App\PrivateActivity\Service\MemberReputation;
use App\Provider\Repository\ProviderProfileRepository;
use App\User\Entity\User;
use App\User\Enum\UserStatus;
use App\User\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Ulid;

/**
 * Profil public d'un membre (13/10, maquette docs/maquettes/detail-profil) :
 * l'organisateur d'une activité gratuite devient consultable — niveau et
 * réputation, badges, activités organisées, participations, avis reçus,
 * photos. Seules les activités que le visiteur a le droit de voir sont
 * listées (PrivateActivityVoter), et le nom s'affiche « Prénom N. ».
 */
final class MemberProfileController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly MemberReputation $reputation,
        private readonly PrivateActivityRepository $activities,
        private readonly ParticipationRepository $participations,
        private readonly MemberReviewRepository $reviews,
        private readonly ProviderProfileRepository $providers,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route(path: ['fr' => '/membres/{id}', 'en' => '/en/members/{id}'], name: 'app_member_profile', requirements: ['id' => '[0-9A-HJKMNP-TV-Za-hjkmnp-tv-z]{26}'], methods: ['GET'])]
    public function show(string $id): Response
    {
        $member = Ulid::isValid($id) ? $this->users->find(Ulid::fromString($id)) : null;
        if (!$member instanceof User || $member->isDeleted() || UserStatus::Suspended === $member->getStatus()) {
            throw $this->createNotFoundException('Ce membre est introuvable.');
        }

        // Un professionnel a déjà sa fiche publique.
        $provider = $member->isProvider() ? $this->providers->findOneByUser($member) : null;
        if (null !== $provider && null !== $provider->getSlug()) {
            return $this->redirectToRoute('app_provider_profile', ['slug' => $provider->getSlug()]);
        }

        $now = new \DateTimeImmutable();
        $organized = array_values(array_filter(
            $this->activities->findByOrganizer($member),
            fn (PrivateActivity $a): bool => !$a->getStatus()->isUnpublished() && $this->isGranted(PrivateActivityVoter::VIEW, $a),
        ));
        usort($organized, static fn (PrivateActivity $a, PrivateActivity $b): int => ($b->getScheduledAt() <=> $a->getScheduledAt()));

        $participated = array_values(array_filter(array_map(
            static fn (Participation $p): ?PrivateActivity => ParticipationStatus::Accepted === $p->getStatus() ? $p->getPrivateActivity() : null,
            $this->participations->findByParticipant($member),
        ), fn (?PrivateActivity $a): bool => null !== $a && $a->getOrganizer() !== $member && $this->isGranted(PrivateActivityVoter::VIEW, $a)));

        $photos = array_values(array_filter(
            $this->entityManager->getRepository(Photo::class)->findBy(['author' => $member], ['createdAt' => 'DESC'], 40),
            fn (Photo $p): bool => null !== ($a = $p->getAlbum()?->getPrivateActivity()) && $this->isGranted(PrivateActivityVoter::VIEW_ALBUM, $a),
        ));

        return $this->render('member/profile.html.twig', [
            'member' => $member,
            'rep' => $this->reputation->of($member),
            'organized' => $organized,
            'participated' => $participated,
            'reviews' => $this->reviews->findForOrganizer($member, 20),
            'photos' => \array_slice($photos, 0, 12),
            'now' => $now,
            'is_me' => $this->getUser() === $member,
        ]);
    }
}
