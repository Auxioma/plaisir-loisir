<?php

declare(strict_types=1);

namespace App\User\Service;

use App\Favorite\Repository\FavoriteRepository;
use App\Messaging\Entity\Message;
use App\Messaging\Repository\ConversationRepository;
use App\PrivateActivity\Repository\ParticipationRepository;
use App\PrivateActivity\Repository\PrivateActivityRepository;
use App\Quote\Entity\ServiceRequest;
use App\Quote\Repository\ServiceRequestRepository;
use App\Review\Repository\ReviewRepository;
use App\User\Entity\Address;
use App\User\Entity\User;

/**
 * Export des données personnelles (§26 du CDC : « export des données lorsque
 * requis », droit à la portabilité de l'article 20 du RGPD).
 *
 * Rassemble ce que l'utilisateur a lui-même produit sur la plateforme — pas
 * un dump de toutes les tables qui le référencent (les avis reçus par un
 * prestataire, par exemple, ne sont pas SES données personnelles à lui, ce
 * sont celles de ses clients).
 */
final class AccountDataExporter
{
    public function __construct(
        private readonly FavoriteRepository $favorites,
        private readonly ServiceRequestRepository $serviceRequests,
        private readonly PrivateActivityRepository $privateActivities,
        private readonly ParticipationRepository $participations,
        private readonly ReviewRepository $reviews,
        private readonly ConversationRepository $conversations,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function export(User $user): array
    {
        return [
            'compte' => [
                'email' => $user->getEmail(),
                'prenom' => $user->getFirstName(),
                'nom' => $user->getLastName(),
                'telephone' => $user->getPhone(),
                'membre_depuis' => $user->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            ],
            'adresses' => array_map($this->address(...), $user->getAddresses()->toArray()),
            'favoris' => [
                'activites' => array_map(static fn ($s): string => $s->getTitle(), $this->favorites->findServicesForUser($user)),
                'destinations' => array_map(static fn ($d): string => $d->getName(), $this->favorites->findDestinationsForUser($user)),
            ],
            'demandes_de_devis' => array_map($this->serviceRequest(...), $this->serviceRequests->findByClient($user)),
            'activites_privees_organisees' => array_map(
                static fn ($a): array => ['titre' => $a->getTitle(), 'date' => $a->getScheduledAt()?->format(\DateTimeInterface::ATOM)],
                $this->privateActivities->findByOrganizer($user),
            ),
            'participations' => array_map(
                static fn ($p): array => [
                    'activite' => $p->getPrivateActivity()?->getTitle(),
                    'statut' => $p->getStatus()->value,
                ],
                $this->participations->findByParticipant($user),
            ),
            'avis_deposes' => array_map(
                static fn ($r): array => [
                    'prestataire' => $r->getProvider()?->getDisplayName(),
                    'note' => $r->getRating(),
                    'commentaire' => $r->getComment(),
                    'date' => $r->getCreatedAt()?->format(\DateTimeInterface::ATOM),
                ],
                $this->reviews->findByAuthor($user),
            ),
            'messages_envoyes' => $this->messagesSentBy($user),
        ];
    }

    /**
     * @return array{destinataire: string, contenu: string, date: string|null}[]
     */
    private function messagesSentBy(User $user): array
    {
        $sent = [];

        foreach ($this->conversations->findForUser($user) as $conversation) {
            $other = $conversation->getClient() === $user
                ? $conversation->getProvider()?->getDisplayName()
                : trim(($conversation->getClient()?->getFirstName() ?? '').' '.($conversation->getClient()?->getLastName() ?? ''));

            foreach ($conversation->getMessages() as $message) {
                if ($message->getAuthor() === $user) {
                    $sent[] = $this->message($message, $other ?? '');
                }
            }
        }

        return $sent;
    }

    /**
     * @return array{destinataire: string, contenu: string, date: string|null}
     */
    private function message(Message $message, string $other): array
    {
        return [
            'destinataire' => $other,
            'contenu' => $message->getBody(),
            'date' => $message->getCreatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * @return array{libelle: string, ligne1: string, ligne2: string|null, ville: string, code_postal: string, pays: string}
     */
    private function address(Address $address): array
    {
        return [
            'libelle' => $address->getLabel(),
            'ligne1' => $address->getLine1(),
            'ligne2' => $address->getLine2(),
            'ville' => $address->getCity(),
            'code_postal' => $address->getPostalCode(),
            'pays' => $address->getCountry(),
        ];
    }

    /**
     * @return array{titre: string, description: string, statut: string, date: string|null}
     */
    private function serviceRequest(ServiceRequest $request): array
    {
        return [
            'titre' => $request->getTitle(),
            'description' => $request->getDescription(),
            'statut' => $request->getStatus()->value,
            'date' => $request->getCreatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }
}
