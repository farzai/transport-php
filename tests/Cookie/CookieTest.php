<?php

declare(strict_types=1);

use Farzai\Transport\Cookie\Cookie;
use Farzai\Transport\Cookie\CookieJar;

describe('Cookie', function () {
    it('creates a cookie with basic attributes', function () {
        $cookie = new Cookie('session_id', 'abc123');

        expect($cookie->getName())->toBe('session_id');
        expect($cookie->getValue())->toBe('abc123');
        expect($cookie->getPath())->toBe('/');
        expect($cookie->getDomain())->toBeNull();
        expect($cookie->isSecure())->toBeFalse();
        expect($cookie->isHttpOnly())->toBeFalse();
    });

    it('creates a cookie with all attributes', function () {
        $expiresAt = time() + 3600;
        $cookie = new Cookie(
            'auth_token',
            'xyz789',
            $expiresAt,
            'example.com',
            '/api',
            true,
            true,
            'Strict'
        );

        expect($cookie->getName())->toBe('auth_token');
        expect($cookie->getValue())->toBe('xyz789');
        expect($cookie->getExpiresAt())->toBe($expiresAt);
        expect($cookie->getDomain())->toBe('example.com');
        expect($cookie->getPath())->toBe('/api');
        expect($cookie->isSecure())->toBeTrue();
        expect($cookie->isHttpOnly())->toBeTrue();
        expect($cookie->getSameSite())->toBe('Strict');
    });

    it('identifies session cookies', function () {
        $sessionCookie = new Cookie('session', 'value');
        $persistentCookie = new Cookie('remember', 'value', time() + 3600);

        expect($sessionCookie->isSessionCookie())->toBeTrue();
        expect($persistentCookie->isSessionCookie())->toBeFalse();
    });

    it('checks if cookie is expired', function () {
        $expiredCookie = new Cookie('old', 'value', time() - 3600);
        $validCookie = new Cookie('new', 'value', time() + 3600);

        expect($expiredCookie->isExpired())->toBeTrue();
        expect($validCookie->isExpired())->toBeFalse();
    });

    it('matches exact domain', function () {
        $cookie = new Cookie('test', 'value', null, 'example.com');

        expect($cookie->matchesDomain('example.com'))->toBeTrue();
        expect($cookie->matchesDomain('www.example.com'))->toBeTrue();
        expect($cookie->matchesDomain('other.com'))->toBeFalse();
    });

    it('matches subdomain with dot prefix', function () {
        $cookie = new Cookie('test', 'value', null, '.example.com');

        expect($cookie->matchesDomain('example.com'))->toBeTrue();
        expect($cookie->matchesDomain('www.example.com'))->toBeTrue();
        expect($cookie->matchesDomain('api.example.com'))->toBeTrue();
        expect($cookie->matchesDomain('other.com'))->toBeFalse();
    });

    it('matches path correctly', function () {
        $cookie = new Cookie('test', 'value', null, null, '/api');

        expect($cookie->matchesPath('/api'))->toBeTrue();
        expect($cookie->matchesPath('/api/users'))->toBeTrue();
        expect($cookie->matchesPath('/api/v2/users'))->toBeTrue();
        expect($cookie->matchesPath('/app'))->toBeFalse();
        expect($cookie->matchesPath('/apiv2'))->toBeFalse();
    });

    it('matches path with trailing slash', function () {
        $cookie = new Cookie('test', 'value', null, null, '/api/');

        expect($cookie->matchesPath('/api/'))->toBeTrue();
        expect($cookie->matchesPath('/api/users'))->toBeTrue();
    });

    it('matches URL correctly', function () {
        $cookie = new Cookie('test', 'value', null, 'example.com', '/api', true);

        expect($cookie->matchesUrl('https://example.com/api', true))->toBeTrue();
        expect($cookie->matchesUrl('https://example.com/api/users', true))->toBeTrue();
        expect($cookie->matchesUrl('http://example.com/api', false))->toBeFalse(); // Secure cookie on non-secure request
        expect($cookie->matchesUrl('https://other.com/api', true))->toBeFalse();
    });

    it('generates Set-Cookie header', function () {
        $cookie = new Cookie('session_id', 'abc123', null, 'example.com', '/', true, true, 'Lax');

        $header = $cookie->toSetCookieHeader();

        expect($header)->toContain('session_id=abc123');
        expect($header)->toContain('Domain=example.com');
        expect($header)->toContain('Secure');
        expect($header)->toContain('HttpOnly');
        expect($header)->toContain('SameSite=Lax');
    });

    it('generates Cookie header for request', function () {
        $cookie = new Cookie('token', 'xyz789');

        expect($cookie->toCookieHeader())->toBe('token=xyz789');
    });

    it('creates cookie with updated value', function () {
        $original = new Cookie('counter', '1', null, 'example.com');
        $updated = $original->withValue('2');

        expect($original->getValue())->toBe('1');
        expect($updated->getValue())->toBe('2');
        expect($updated->getDomain())->toBe('example.com');
    });

    it('parses Set-Cookie header', function () {
        $header = 'session_id=abc123; Expires=Wed, 21 Oct 2025 07:28:00 GMT; Domain=example.com; Path=/; Secure; HttpOnly; SameSite=Strict';

        $cookie = Cookie::fromSetCookieHeader($header);

        expect($cookie->getName())->toBe('session_id');
        expect($cookie->getValue())->toBe('abc123');
        expect($cookie->getDomain())->toBe('example.com');
        expect($cookie->getPath())->toBe('/');
        expect($cookie->isSecure())->toBeTrue();
        expect($cookie->isHttpOnly())->toBeTrue();
        expect($cookie->getSameSite())->toBe('Strict');
    });

    it('parses Set-Cookie header with Max-Age', function () {
        $header = 'token=xyz; Max-Age=3600; Domain=example.com';

        $cookie = Cookie::fromSetCookieHeader($header);

        expect($cookie->getName())->toBe('token');
        expect($cookie->getExpiresAt())->toBeGreaterThan(time());
    });

    it('throws exception for invalid cookie name', function () {
        expect(fn () => new Cookie('invalid name', 'value'))
            ->toThrow(\InvalidArgumentException::class);

        expect(fn () => new Cookie('invalid;name', 'value'))
            ->toThrow(\InvalidArgumentException::class);
    });

    it('throws exception for invalid SameSite value', function () {
        expect(fn () => new Cookie('test', 'value', null, null, '/', false, false, 'Invalid'))
            ->toThrow(\InvalidArgumentException::class);
    });

    it('generates unique identifier', function () {
        $cookie1 = new Cookie('session', 'value', null, 'example.com', '/');
        $cookie2 = new Cookie('session', 'value', null, 'example.com', '/api');
        $cookie3 = new Cookie('session', 'value', null, 'other.com', '/');

        expect($cookie1->getIdentifier())->not->toBe($cookie2->getIdentifier());
        expect($cookie1->getIdentifier())->not->toBe($cookie3->getIdentifier());
    });

    it('converts domain to lowercase', function () {
        $cookie = new Cookie('test', 'value', null, 'Example.COM');

        expect($cookie->getDomain())->toBe('example.com');
    });
});

describe('CookieJar', function () {
    it('stores and retrieves cookies', function () {
        $jar = new CookieJar;
        $cookie = new Cookie('session', 'abc123', null, 'example.com');

        $jar->setCookie($cookie);

        $retrieved = $jar->getCookie('session', 'example.com');
        expect($retrieved)->not->toBeNull();
        expect($retrieved->getValue())->toBe('abc123');
    });

    it('replaces cookie with same name, domain, and path', function () {
        $jar = new CookieJar;
        $cookie1 = new Cookie('token', 'old', null, 'example.com');
        $cookie2 = new Cookie('token', 'new', null, 'example.com');

        $jar->setCookie($cookie1);
        $jar->setCookie($cookie2);

        $retrieved = $jar->getCookie('token', 'example.com');
        expect($retrieved->getValue())->toBe('new');
        expect($jar->count())->toBe(1);
    });

    it('stores multiple cookies with different attributes', function () {
        $jar = new CookieJar;

        $jar->setCookie(new Cookie('token', 'value1', null, 'example.com', '/'));
        $jar->setCookie(new Cookie('token', 'value2', null, 'example.com', '/api'));
        $jar->setCookie(new Cookie('session', 'value3', null, 'other.com', '/'));

        expect($jar->count())->toBe(3);
    });

    it('gets cookies for URL', function () {
        $jar = new CookieJar;

        $jar->setCookie(new Cookie('cookie1', 'value1', null, 'example.com', '/'));
        $jar->setCookie(new Cookie('cookie2', 'value2', null, 'example.com', '/api'));
        $jar->setCookie(new Cookie('cookie3', 'value3', null, 'other.com', '/'));

        $cookies = $jar->getCookiesForUrl('https://example.com/api/users');

        expect($cookies)->toHaveCount(2);
        expect($cookies[0]->getName())->toBe('cookie2'); // More specific path first
        expect($cookies[1]->getName())->toBe('cookie1');
    });

    it('respects secure flag in URL matching', function () {
        $jar = new CookieJar;
        $secureCookie = new Cookie('secure', 'value', null, 'example.com', '/', true);

        $jar->setCookie($secureCookie);

        $httpsMatches = $jar->getCookiesForUrl('https://example.com/', true);
        $httpMatches = $jar->getCookiesForUrl('http://example.com/', false);

        expect($httpsMatches)->toHaveCount(1);
        expect($httpMatches)->toHaveCount(0);
    });

    it('removes expired cookies automatically', function () {
        $jar = new CookieJar;

        $jar->setCookie(new Cookie('expired', 'value', time() - 3600));
        $jar->setCookie(new Cookie('valid', 'value', time() + 3600));

        expect($jar->count())->toBe(1);
    });

    it('removes specific cookie', function () {
        $jar = new CookieJar;
        $jar->setCookie(new Cookie('token', 'value', null, 'example.com'));

        $jar->removeCookie('token', 'example.com');

        expect($jar->count())->toBe(0);
    });

    it('clears all cookies', function () {
        $jar = new CookieJar;

        $jar->setCookie(new Cookie('cookie1', 'value1'));
        $jar->setCookie(new Cookie('cookie2', 'value2'));

        $jar->clear();

        expect($jar->count())->toBe(0);
        expect($jar->isEmpty())->toBeTrue();
    });

    it('adds cookies from Set-Cookie headers', function () {
        $jar = new CookieJar;
        $headers = [
            'session_id=abc123; Path=/; HttpOnly',
            'token=xyz789; Domain=example.com; Secure',
        ];

        $jar->addFromSetCookieHeaders($headers, 'https://example.com');

        expect($jar->count())->toBe(2);
    });

    it('generates Cookie header for URL', function () {
        $jar = new CookieJar;

        $jar->setCookie(new Cookie('session', 'abc123', null, 'example.com'));
        $jar->setCookie(new Cookie('token', 'xyz789', null, 'example.com'));

        $header = $jar->getCookieHeaderForUrl('https://example.com/');

        expect($header)->toContain('session=abc123');
        expect($header)->toContain('token=xyz789');
        expect($header)->toContain('; ');
    });

    it('returns null when no cookies match URL', function () {
        $jar = new CookieJar;
        $jar->setCookie(new Cookie('test', 'value', null, 'example.com'));

        $header = $jar->getCookieHeaderForUrl('https://other.com/');

        expect($header)->toBeNull();
    });

    it('exports and imports cookies', function () {
        $jar1 = new CookieJar;
        $jar1->setCookie(new Cookie('cookie1', 'value1', null, 'example.com'));
        $jar1->setCookie(new Cookie('cookie2', 'value2', time() + 3600, 'example.com'));

        $data = $jar1->toArray();

        $jar2 = new CookieJar;
        $jar2->fromArray($data);

        expect($jar2->count())->toBe(2);
        expect($jar2->getCookie('cookie1', 'example.com')->getValue())->toBe('value1');
    });

    it('handles session cookie persistence', function () {
        $jar = CookieJar::withSessionPersistence();
        $sessionCookie = new Cookie('session', 'value');

        $jar->setCookie($sessionCookie);

        expect($jar->count())->toBe(1);
    });

    it('skips invalid cookies when adding from headers', function () {
        $jar = new CookieJar;
        $headers = [
            'valid=value; Path=/',
            '', // Empty header
            'invalid', // No value
        ];

        $jar->addFromSetCookieHeaders($headers, 'https://example.com');

        // Should only add the valid cookie
        expect($jar->count())->toBe(1);
    });

    it('sorts cookies by path specificity', function () {
        $jar = new CookieJar;

        $jar->setCookie(new Cookie('root', 'value', null, 'example.com', '/'));
        $jar->setCookie(new Cookie('api', 'value', null, 'example.com', '/api'));
        $jar->setCookie(new Cookie('users', 'value', null, 'example.com', '/api/users'));

        $cookies = $jar->getCookiesForUrl('https://example.com/api/users/123');

        // More specific paths should come first
        expect($cookies[0]->getName())->toBe('users');
        expect($cookies[1]->getName())->toBe('api');
        expect($cookies[2]->getName())->toBe('root');
    });
});
