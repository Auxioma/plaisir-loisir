<?php

declare(strict_types=1);

namespace App\Tests\Booking\Workflow;

use App\Booking\Entity\Booking;
use App\Booking\Enum\BookingStatus;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Valide la configuration réelle du workflow "booking" (transitions et gardes).
 * Aucune base de données : les transitions sont appliquées en mémoire.
 */
final class BookingWorkflowTest extends KernelTestCase
{
    private function workflow(): WorkflowInterface
    {
        self::bootKernel();

        /** @var WorkflowInterface $workflow */
        $workflow = self::getContainer()->get('state_machine.booking');

        return $workflow;
    }

    public function testNominalPathFromPendingToCompleted(): void
    {
        $workflow = $this->workflow();
        $booking = new Booking();

        self::assertTrue($workflow->can($booking, 'confirm'));

        $workflow->apply($booking, 'confirm');
        self::assertSame(BookingStatus::Confirmed, $booking->getStatus());

        $workflow->apply($booking, 'start');
        self::assertSame(BookingStatus::InProgress, $booking->getStatus());

        $workflow->apply($booking, 'complete');
        self::assertSame(BookingStatus::Completed, $booking->getStatus());
    }

    public function testCancelFromPending(): void
    {
        $workflow = $this->workflow();
        $booking = new Booking();

        $workflow->apply($booking, 'cancel');

        self::assertSame(BookingStatus::Cancelled, $booking->getStatus());
    }

    public function testRefundFromConfirmed(): void
    {
        $workflow = $this->workflow();
        $booking = new Booking();
        $workflow->apply($booking, 'confirm');

        $workflow->apply($booking, 'refund');

        self::assertSame(BookingStatus::Refunded, $booking->getStatus());
    }

    public function testCannotCompleteDirectlyFromPending(): void
    {
        $workflow = $this->workflow();
        $booking = new Booking();

        self::assertFalse($workflow->can($booking, 'complete'));
    }
}
