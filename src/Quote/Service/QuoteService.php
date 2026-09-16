<?php

declare(strict_types=1);

namespace App\Quote\Service;

use App\Catalog\Entity\Category;
use App\Notification\Enum\NotificationCategory;
use App\Notification\Service\NotificationService;
use App\Provider\Entity\ProviderProfile;
use App\Provider\Repository\ProviderProfileRepository;
use App\Quote\Entity\Quote;
use App\Quote\Entity\ServiceRequest;
use App\Quote\Repository\QuoteRepository;
use App\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Logique métier des devis : demande de besoin, propositions et acceptation.
 */
final class QuoteService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly QuoteRepository $quotes,
        private readonly ProviderProfileRepository $providers,
        private readonly NotificationService $notifications,
    ) {
    }

    /**
     * Notifie les prestataires vérifiés du métier demandé (§15 du CDC :
     * « nouvelle demande pertinente »). Même filtre que ProviderRequestController
     * (métier uniquement, pas de rayon — cf. ProviderSearchController).
     */
    public function createRequest(User $client, Category $category, string $title, string $description): ServiceRequest
    {
        $request = (new ServiceRequest())
            ->setClient($client)
            ->setCategory($category)
            ->setTitle($title)
            ->setDescription($description);

        $this->entityManager->persist($request);
        $this->entityManager->flush();

        foreach ($this->providers->search($category, null) as $provider) {
            $owner = $provider->getUser();
            if (null !== $owner) {
                $this->notifications->notify(
                    $owner,
                    NotificationCategory::Quote,
                    'Nouvelle demande',
                    \sprintf('Une nouvelle demande « %s » correspond à votre métier.', $title),
                );
            }
        }

        return $request;
    }

    /**
     * @throws \InvalidArgumentException si la demande est clôturée ou si l'annonceur
     *                                   a déjà proposé un devis
     */
    public function submitQuote(ServiceRequest $request, ProviderProfile $provider, string $amount, ?string $message = null): Quote
    {
        if (!$request->isOpen()) {
            throw new \InvalidArgumentException('Cette demande est clôturée.');
        }

        if (null !== $this->quotes->findOneByRequestAndProvider($request, $provider)) {
            throw new \InvalidArgumentException('Vous avez déjà proposé un devis pour cette demande.');
        }

        $quote = (new Quote())
            ->setProvider($provider)
            ->setAmount($amount)
            ->setMessage($message);
        $request->addQuote($quote);

        $this->entityManager->persist($quote);
        $this->entityManager->flush();

        $client = $request->getClient();
        if (null !== $client) {
            $this->notifications->notify(
                $client,
                NotificationCategory::Quote,
                'Nouvelle réponse',
                \sprintf('%s a répondu à votre demande « %s ».', $provider->getDisplayName(), $request->getTitle()),
            );
        }

        return $quote;
    }

    /**
     * Accepte un devis : refuse les autres devis de la demande et la clôture.
     *
     * @throws \InvalidArgumentException si le devis n'est rattaché à aucune demande
     */
    public function accept(Quote $quote): void
    {
        $request = $quote->getServiceRequest();
        if (null === $request) {
            throw new \InvalidArgumentException('Ce devis n\'est rattaché à aucune demande.');
        }

        $quote->accept();
        foreach ($request->getQuotes() as $other) {
            if ($other !== $quote) {
                $other->decline();
            }
        }
        $request->close();

        $this->entityManager->flush();

        $owner = $quote->getProvider()?->getUser();
        if (null !== $owner) {
            $this->notifications->notify(
                $owner,
                NotificationCategory::Quote,
                'Devis accepté',
                \sprintf('Votre devis pour « %s » a été accepté.', $request->getTitle()),
            );
        }
    }

    public function decline(Quote $quote): void
    {
        $quote->decline();
        $this->entityManager->flush();
    }
}
