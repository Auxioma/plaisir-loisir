<?php

declare(strict_types=1);

namespace App\Tests\Admin\Security;

use App\Admin\Entity\Report;
use App\Admin\Security\ReportVoter;
use App\User\Entity\User;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * §33.2 du CDC : famille REPORT_VIEW / PROCESS.
 */
final class ReportVoterTest extends TestCase
{
    public function testTheReporterCanViewTheirOwnReportButNotProcessIt(): void
    {
        $reporter = new User();
        $report = (new Report())->setReporter($reporter);
        $voter = new ReportVoter();

        self::assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($this->tokenFor($reporter, []), $report, [ReportVoter::VIEW]));
        self::assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($this->tokenFor($reporter, []), $report, [ReportVoter::PROCESS]));
    }

    public function testAStrangerCannotViewSomeoneElsesReport(): void
    {
        $report = (new Report())->setReporter(new User());
        $voter = new ReportVoter();

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $voter->vote($this->tokenFor(new User(), []), $report, [ReportVoter::VIEW]),
        );
    }

    public function testAnAdminCanViewAndProcessAnyReport(): void
    {
        $report = (new Report())->setReporter(new User());
        $voter = new ReportVoter();

        $admin = $this->tokenFor(new User(), ['ROLE_ADMIN']);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($admin, $report, [ReportVoter::VIEW]));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($admin, $report, [ReportVoter::PROCESS]));
    }

    /**
     * @param list<string> $roles
     */
    private function tokenFor(User $user, array $roles): TokenInterface
    {
        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($user);
        $token->method('getRoleNames')->willReturn($roles);

        return $token;
    }
}
