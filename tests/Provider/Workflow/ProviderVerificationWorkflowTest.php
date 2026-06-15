<?php

declare(strict_types=1);

namespace App\Tests\Provider\Workflow;

use App\Provider\Entity\ProviderProfile;
use App\Provider\Enum\ProviderStatus;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Valide la configuration réelle du workflow "provider_verification"
 * (config/packages/workflow.yaml) ainsi que le pont getMarking()/setMarking().
 *
 * Aucune base de données n'est nécessaire : on applique les transitions en mémoire.
 */
final class ProviderVerificationWorkflowTest extends KernelTestCase
{
    private function workflow(): WorkflowInterface
    {
        self::bootKernel();

        /** @var WorkflowInterface $workflow */
        $workflow = self::getContainer()->get('state_machine.provider_verification');

        return $workflow;
    }

    public function testNominalPathFromDraftToVerified(): void
    {
        $workflow = $this->workflow();
        $profile = new ProviderProfile();

        self::assertTrue($workflow->can($profile, 'submit'));

        $workflow->apply($profile, 'submit');
        self::assertSame(ProviderStatus::PendingVerification, $profile->getStatus());

        $workflow->apply($profile, 'approve');
        self::assertSame(ProviderStatus::Verified, $profile->getStatus());
    }

    public function testRejectionLeadsToSuspended(): void
    {
        $workflow = $this->workflow();
        $profile = new ProviderProfile();
        $workflow->apply($profile, 'submit');

        $workflow->apply($profile, 'reject');

        self::assertSame(ProviderStatus::Suspended, $profile->getStatus());
    }

    public function testSuspendAndReinstateAVerifiedProvider(): void
    {
        $workflow = $this->workflow();
        $profile = (new ProviderProfile())->setStatus(ProviderStatus::Verified);

        $workflow->apply($profile, 'suspend');
        self::assertSame(ProviderStatus::Suspended, $profile->getStatus());

        $workflow->apply($profile, 'reinstate');
        self::assertSame(ProviderStatus::Verified, $profile->getStatus());
    }

    public function testCannotApproveDirectlyFromDraft(): void
    {
        $workflow = $this->workflow();
        $profile = new ProviderProfile();

        self::assertFalse($workflow->can($profile, 'approve'));
    }
}
