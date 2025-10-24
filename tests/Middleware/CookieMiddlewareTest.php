<?php

declare(strict_types=1);

use Farzai\Transport\Cookie\Cookie;
use Farzai\Transport\Cookie\CookieJar;
use Farzai\Transport\Middleware\CookieMiddleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

describe('CookieMiddleware', function () {
    it('adds cookies from jar to request', function () {
        $jar = new CookieJar;
        $jar->setCookie(new Cookie('session_id', 'abc123', null, 'example.com', '/'));

        $middleware = new CookieMiddleware($jar);

        $request = new Request('GET', 'https://example.com/api');
        $middleware->handle($request, function ($req) {
            expect($req->getHeaderLine('Cookie'))->toBe('session_id=abc123');

            return new Response(200);
        });
    });

    it('extracts cookies from Set-Cookie response headers', function () {
        $jar = new CookieJar;
        $middleware = new CookieMiddleware($jar);

        $request = new Request('GET', 'https://example.com/login');
        $response = new Response(200, [
            'Set-Cookie' => 'auth_token=xyz789; Path=/; HttpOnly',
        ]);

        $middleware->handle($request, fn () => $response);

        $cookies = $jar->getAllCookies();
        expect($cookies)->toHaveCount(1);
        expect($cookies[0]->getName())->toBe('auth_token');
        expect($cookies[0]->getValue())->toBe('xyz789');
    });

    it('merges with existing Cookie header', function () {
        $jar = new CookieJar;
        $jar->setCookie(new Cookie('session_id', 'abc123', null, 'example.com', '/'));

        $middleware = new CookieMiddleware($jar);

        $request = new Request('GET', 'https://example.com/api', [
            'Cookie' => 'user_pref=dark_mode',
        ]);

        $middleware->handle($request, function ($req) {
            $cookieHeader = $req->getHeaderLine('Cookie');
            expect($cookieHeader)->toContain('user_pref=dark_mode');
            expect($cookieHeader)->toContain('session_id=abc123');

            return new Response(200);
        });
    });

    it('handles requests with no matching cookies', function () {
        $jar = new CookieJar;
        $jar->setCookie(new Cookie('session', 'value', null, 'other.com', '/'));

        $middleware = new CookieMiddleware($jar);

        $request = new Request('GET', 'https://example.com/api');
        $middleware->handle($request, function ($req) {
            expect($req->getHeaderLine('Cookie'))->toBe('');

            return new Response(200);
        });
    });

    it('handles responses with no Set-Cookie headers', function () {
        $jar = new CookieJar;
        $middleware = new CookieMiddleware($jar);

        $request = new Request('GET', 'https://example.com/api');
        $response = new Response(200);

        $middleware->handle($request, fn () => $response);

        expect($jar->getAllCookies())->toHaveCount(0);
    });

    it('respects secure flag for HTTPS requests', function () {
        $jar = new CookieJar;
        $jar->setCookie(new Cookie('secure_token', 'secret', null, 'example.com', '/', true));

        $middleware = new CookieMiddleware($jar);

        // HTTPS request should include secure cookie
        $httpsRequest = new Request('GET', 'https://example.com/api');
        $middleware->handle($httpsRequest, function ($req) {
            expect($req->getHeaderLine('Cookie'))->toBe('secure_token=secret');

            return new Response(200);
        });
    });

    it('excludes secure cookies from HTTP requests', function () {
        $jar = new CookieJar;
        $jar->setCookie(new Cookie('secure_token', 'secret', null, 'example.com', '/', true));

        $middleware = new CookieMiddleware($jar);

        // HTTP request should NOT include secure cookie
        $httpRequest = new Request('GET', 'http://example.com/api');
        $middleware->handle($httpRequest, function ($req) {
            expect($req->getHeaderLine('Cookie'))->toBe('');

            return new Response(200);
        });
    });

    it('handles multiple Set-Cookie headers', function () {
        $jar = new CookieJar;
        $middleware = new CookieMiddleware($jar);

        $request = new Request('GET', 'https://example.com/login');
        $response = new Response(200, [
            'Set-Cookie' => [
                'session_id=abc123; Path=/',
                'user_id=42; Path=/; HttpOnly',
                'remember=true; Path=/; Max-Age=2592000',
            ],
        ]);

        $middleware->handle($request, fn () => $response);

        $cookies = $jar->getAllCookies();
        expect($cookies)->toHaveCount(3);
        expect($cookies[0]->getName())->toBe('session_id');
        expect($cookies[1]->getName())->toBe('user_id');
        expect($cookies[2]->getName())->toBe('remember');
    });

    it('returns the cookie jar instance', function () {
        $jar = new CookieJar;
        $middleware = new CookieMiddleware($jar);

        expect($middleware->getCookieJar())->toBe($jar);
    });

    it('can create middleware with default cookie jar', function () {
        $middleware = CookieMiddleware::create();

        expect($middleware->getCookieJar())->toBeInstanceOf(CookieJar::class);
    });

    it('can create middleware with custom cookie jar', function () {
        $jar = new CookieJar;
        $jar->setCookie(new Cookie('existing', 'cookie', null, 'example.com', '/'));

        $middleware = CookieMiddleware::create($jar);

        expect($middleware->getCookieJar())->toBe($jar);
        expect($jar->getAllCookies())->toHaveCount(1);
    });

    it('can create middleware with session persistence', function () {
        $middleware = CookieMiddleware::withSessionPersistence();

        expect($middleware->getCookieJar())->toBeInstanceOf(CookieJar::class);

        // Verify it includes session cookies
        $jar = $middleware->getCookieJar();
        $request = new Request('GET', 'https://example.com/api');
        $response = new Response(200, [
            'Set-Cookie' => 'session=value; Path=/',
        ]);

        $middleware->handle($request, fn () => $response);

        expect($jar->getAllCookies())->toHaveCount(1);
    });

    it('handles complete request-response cycle with cookies', function () {
        $jar = new CookieJar;
        $middleware = new CookieMiddleware($jar);

        // First request: receive cookie from server
        $request1 = new Request('GET', 'https://example.com/login');
        $response1 = new Response(200, [
            'Set-Cookie' => 'session_id=abc123; Path=/; HttpOnly',
        ]);

        $middleware->handle($request1, fn () => $response1);

        // Second request: cookie should be sent automatically
        $request2 = new Request('GET', 'https://example.com/api/user');
        $middleware->handle($request2, function ($req) {
            expect($req->getHeaderLine('Cookie'))->toBe('session_id=abc123');

            return new Response(200);
        });
    });

    it('handles cookie updates from server', function () {
        $jar = new CookieJar;
        $middleware = new CookieMiddleware($jar);

        // First request: receive initial cookie
        $request1 = new Request('GET', 'https://example.com/api');
        $response1 = new Response(200, [
            'Set-Cookie' => 'token=old_value; Path=/',
        ]);

        $middleware->handle($request1, fn () => $response1);

        // Second request: server updates cookie
        $request2 = new Request('GET', 'https://example.com/api');
        $response2 = new Response(200, [
            'Set-Cookie' => 'token=new_value; Path=/',
        ]);

        $middleware->handle($request2, fn () => $response2);

        // Verify cookie was updated
        $cookies = $jar->getAllCookies();
        expect($cookies)->toHaveCount(1);
        expect($cookies[0]->getValue())->toBe('new_value');
    });

    it('respects path restrictions', function () {
        $jar = new CookieJar;
        $jar->setCookie(new Cookie('admin_token', 'secret', null, 'example.com', '/admin'));

        $middleware = new CookieMiddleware($jar);

        // Request to /admin should include cookie
        $adminRequest = new Request('GET', 'https://example.com/admin/users');
        $middleware->handle($adminRequest, function ($req) {
            expect($req->getHeaderLine('Cookie'))->toBe('admin_token=secret');

            return new Response(200);
        });

        // Request to / should NOT include cookie
        $rootRequest = new Request('GET', 'https://example.com/');
        $middleware->handle($rootRequest, function ($req) {
            expect($req->getHeaderLine('Cookie'))->toBe('');

            return new Response(200);
        });
    });

    it('respects domain restrictions', function () {
        $jar = new CookieJar;
        $jar->setCookie(new Cookie('site_token', 'value', null, 'site.com', '/'));

        $middleware = new CookieMiddleware($jar);

        // Request to site.com should include cookie
        $matchingRequest = new Request('GET', 'https://site.com/api');
        $middleware->handle($matchingRequest, function ($req) {
            expect($req->getHeaderLine('Cookie'))->toBe('site_token=value');

            return new Response(200);
        });

        // Request to other.com should NOT include cookie
        $nonMatchingRequest = new Request('GET', 'https://other.com/api');
        $middleware->handle($nonMatchingRequest, function ($req) {
            expect($req->getHeaderLine('Cookie'))->toBe('');

            return new Response(200);
        });
    });
});
