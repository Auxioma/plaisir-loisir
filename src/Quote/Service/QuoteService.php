<?php

declare(strict_types=1);

namespace App\Quote\Service;

use App\Catalog\Entity\Category;
use App\Provider\Entity\ProviderProfile;
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
    ) {
    }

    public function createRequest(User $client, Category $category, string $title, string $description): ServiceRequest
    {
        $request = (new ServiceRequest())
            ->setClient($client)
            ->setCategory($category)
            ->setTitle($title)
            ->setDescription($description);

        $this->entityManager->persist($request);
        $this->entityManager->flush();

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
    }

    public function decline(Quote $quote): void
    {
        $quote->decline();
        $this->entityManager->flush();
    }
}
