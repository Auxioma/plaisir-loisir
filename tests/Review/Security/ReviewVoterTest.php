<?php

declare(strict_types=1);

namespace App\Tests\Review\Security;

use App\Provider\Entity\ProviderProfile;
use App\Quote\Entity\Quote;
use App\Quote\Entity\ServiceRequest;
use App\Review\Entity\Review;
use App\Review\Security\ReviewVoter;
use App\User\Entity\User;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * §4, §33.2 du CDC : « le masquage d'un bouton dans Twig ne constitue jamais
 * une autorisation suffisante ». Ce test vérifie le VRAI verrou, côté Symfony.
 */
final class ReviewVoterTest extends TestCase
{
    public function testTheRequestingClientCanReviewAnAcceptedQuote(): void
    {
        $client = new User();
        $request = (new ServiceRequest())->setClient($client);
        $quote = (new Quote())->setServiceRequest($request)->setProvider(new ProviderProfile())->setAmount('100.00');
        $quote->accept();

        $voter = new ReviewVoter();

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $voter->vote($this->tokenFor($client), $quote, [ReviewVoter::CREATE]),
        );
    }

    public function testANonAcceptedQuoteCannotBeReviewed(): void
    {
        $client = new User();
        $request = (new ServiceRequest())->setClient($client);
        $quote = (new Quote())->setServiceRequest($request)->setProvider(new ProviderProfile())->setAmount('100.00');
        // Statut par défaut : en attente.

        $voter = new ReviewVoter();

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $voter->vote($this->tokenFor($client), $quote, [ReviewVoter::CREATE]),
        );
    }

    public function testAnotherClientCannotReviewSomeoneElsesQuote(): void
    {
        $owner = new User();
        $intrus = new User();
        $request = (new ServiceRequest())->setClient($owner);
        $quote = (new Quote())->setServiceRequest($request)->setProvider(new ProviderProfile())->setAmount('100.00');
        $quote->accept();

        $voter = new ReviewVoter();

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $voter->vote($this->tokenFor($intrus), $quote, [ReviewVoter::CREATE]),
        );
    }

    public function testOnlyTheNotedProviderCanReply(): void
    {
        $owner = new User();
        $review = (new Review())->setProvider((new ProviderProfile())->setUser($owner));

        $voter = new ReviewVoter();

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $voter->vote($this->tokenFor($owner), $review, [ReviewVoter::REPLY]),
        );
        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $voter->vote($this->tokenFor(new User()), $review, [ReviewVoter::REPLY]),
        );
    }

    private function tokenFor(User $user): TokenInterface
    {
        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        return $token;
    }
}
