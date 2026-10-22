<?php

declare(strict_types=1);

namespace App\Tests\User;

use App\User\Security\LoginSuccessHandler;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\HttpUtils;

final class LoginSuccessHandlerTest extends TestCase
{
    #[DataProvider('redirects')]
    public function testRoleDeterminesTheDashboard(array $roles, ?string $target, string $route, string $destination): void
    {
        $urls = $this->createMock(UrlGeneratorInterface::class);
        $urls->expects(self::once())->method('generate')->with($route)->willReturn($destination);
        $handler = new LoginSuccessHandler(new HttpUtils(), $urls);
        $token = $this->createStub(TokenInterface::class);
        $token->method('getRoleNames')->willReturn($roles);
        $request = Request::create('https://example.com/login', 'POST', $target ? ['_target_path' => $target] : []);
        $response = $handler->onAuthenticationSuccess($request, $token);
        self::assertSame('https://example.com'.$destination, $response->headers->get('Location'));
    }

    #[DataProvider('bookingTargets')]
    public function testOnlyLocalBookingTargetsResume(string $target, bool $resume): void
    {
        $urls = $this->createStub(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturn('/compte/tableau-de-bord');
        $handler = new LoginSuccessHandler(new HttpUtils(), $urls);
        $token = $this->createStub(TokenInterface::class);
        $token->method('getRoleNames')->willReturn(['ROLE_USER']);
        $request = Request::create('https://example.com/login', 'POST', ['_target_path' => $target]);
        $response = $handler->onAuthenticationSuccess($request, $token);
        $expected = $resume ? (str_starts_with($target, 'https:') ? $target : 'https://example.com'.$target) : 'https://example.com/compte/tableau-de-bord';
        self::assertSame($expected, $response->headers->get('Location'));
    }

    public static function bookingTargets(): iterable
    {
        yield ['/reservations/reprendre', true];
        yield ['/en/bookings/resume', true];
        yield ['https://example.com/reservations/123/paiement?moyen=stripe', true];
        yield ['https://evil.test/reservations/123', false];
        yield ['//evil.test/reservations/123', false];
        yield ['/activites/sortie#reserver', false];
    }

    public static function redirects(): iterable
    {
        yield 'client' => [['ROLE_USER'], null, 'app_account_dashboard', '/compte/tableau-de-bord'];
        yield 'client from landing' => [['ROLE_USER'], '/', 'app_account_dashboard', '/compte/tableau-de-bord'];
        yield 'client from public page' => [['ROLE_USER'], '/activites', 'app_account_dashboard', '/compte/tableau-de-bord'];
        yield 'provider' => [['ROLE_PROVIDER'], null, 'app_pro_dashboard', '/pro/tableau-de-bord'];
        yield 'administrator' => [['ROLE_ADMIN'], null, 'admin', '/admin'];
    }
}
