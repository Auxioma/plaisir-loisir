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

    public static function redirects(): iterable
    {
        yield 'client' => [['ROLE_USER'], null, 'app_account_dashboard', '/compte/tableau-de-bord'];
        yield 'client from landing' => [['ROLE_USER'], '/', 'app_account_dashboard', '/compte/tableau-de-bord'];
        yield 'client from public page' => [['ROLE_USER'], '/activites', 'app_account_dashboard', '/compte/tableau-de-bord'];
        yield 'provider' => [['ROLE_PROVIDER'], null, 'app_pro_dashboard', '/pro/tableau-de-bord'];
        yield 'administrator' => [['ROLE_ADMIN'], null, 'admin', '/admin'];
    }
}
