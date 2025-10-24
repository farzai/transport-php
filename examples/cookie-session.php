<?php

/**
 * Cookie Session Example
 *
 * This example demonstrates how to use cookie management for maintaining
 * sessions across HTTP requests, simulating user login flows and authenticated requests.
 */

require __DIR__.'/../vendor/autoload.php';

use Farzai\Transport\Cookie\Cookie;
use Farzai\Transport\Cookie\CookieJar;
use Farzai\Transport\TransportBuilder;

echo "=== Transport PHP Cookie Session Examples ===\n\n";

// Example 1: Automatic Cookie Handling with withCookies()
echo "1. Automatic Cookie Handling\n";
echo str_repeat('-', 50)."\n";

try {
    // Create transport with automatic cookie handling
    $transport = TransportBuilder::make()
        ->withBaseUri('https://httpbin.org')
        ->withCookies() // Enable automatic cookie management
        ->build();

    // First request - server will set cookies
    echo "Making initial request...\n";
    $response1 = $transport->get('/cookies/set')
        ->withQuery(['session' => 'abc123', 'user_id' => '42'])
        ->send();

    echo "Status: {$response1->statusCode()}\n";
    echo "Cookies received and stored automatically\n";

    // Second request - cookies are automatically sent
    echo "\nMaking authenticated request...\n";
    $response2 = $transport->get('/cookies')->send();

    $cookies = $response2->json('cookies');
    echo 'Cookies sent: '.json_encode($cookies, JSON_PRETTY_PRINT)."\n\n";
} catch (\Exception $e) {
    echo "Error: {$e->getMessage()}\n\n";
}

// Example 2: Simulated Login Flow
echo "2. Simulated Login Flow\n";
echo str_repeat('-', 50)."\n";

try {
    $cookieJar = new CookieJar;

    $transport = TransportBuilder::make()
        ->withBaseUri('https://httpbin.org')
        ->withCookieJar($cookieJar)
        ->build();

    // Step 1: Login
    echo "Step 1: Logging in...\n";
    $loginResponse = $transport->post('/cookies/set')
        ->withQuery([
            'auth_token' => 'secret_token_xyz',
            'session_id' => 'sess_'.bin2hex(random_bytes(8)),
        ])
        ->send();

    echo "Login successful! Cookies stored.\n";
    echo "Cookies in jar: {$cookieJar->count()}\n";

    // Step 2: Make authenticated requests
    echo "\nStep 2: Accessing protected resource...\n";
    $profileResponse = $transport->get('/cookies')->send();

    echo "Profile data retrieved with cookies:\n";
    echo json_encode($profileResponse->json('cookies'), JSON_PRETTY_PRINT)."\n\n";
} catch (\Exception $e) {
    echo "Error: {$e->getMessage()}\n\n";
}

// Example 3: Manual Cookie Management
echo "3. Manual Cookie Management\n";
echo str_repeat('-', 50)."\n";

try {
    $cookieJar = new CookieJar;

    // Manually create and add cookies
    $sessionCookie = new Cookie(
        name: 'session_id',
        value: 'manual_session_123',
        expiresAt: time() + 3600, // 1 hour
        domain: 'httpbin.org',
        path: '/',
        secure: true,
        httpOnly: true,
        sameSite: 'Lax'
    );

    $userCookie = new Cookie(
        name: 'user_id',
        value: '999',
        expiresAt: time() + 86400, // 1 day
        domain: 'httpbin.org'
    );

    $cookieJar->setCookie($sessionCookie);
    $cookieJar->setCookie($userCookie);

    echo "Manually added {$cookieJar->count()} cookies\n";

    // Use the cookie jar
    $transport = TransportBuilder::make()
        ->withBaseUri('https://httpbin.org')
        ->withCookieJar($cookieJar)
        ->build();

    $response = $transport->get('/cookies')->send();

    echo "Cookies sent to server:\n";
    echo json_encode($response->json('cookies'), JSON_PRETTY_PRINT)."\n\n";
} catch (\Exception $e) {
    echo "Error: {$e->getMessage()}\n\n";
}

// Example 4: Cookie Inspection and Management
echo "4. Cookie Inspection\n";
echo str_repeat('-', 50)."\n";

try {
    $cookieJar = new CookieJar;

    // Add various cookies
    $cookieJar->setCookie(new Cookie('cookie1', 'value1', null, 'example.com', '/'));
    $cookieJar->setCookie(new Cookie('cookie2', 'value2', time() + 3600, 'example.com', '/api'));
    $cookieJar->setCookie(new Cookie('secure_cookie', 'secret', time() + 7200, 'example.com', '/', true));

    echo "Total cookies: {$cookieJar->count()}\n";
    echo 'Is empty: '.($cookieJar->isEmpty() ? 'Yes' : 'No')."\n\n";

    // Get all cookies
    echo "All cookies:\n";
    foreach ($cookieJar->getAllCookies() as $cookie) {
        echo "  - {$cookie->getName()}: {$cookie->getValue()}";
        echo " (Domain: {$cookie->getDomain()}, Path: {$cookie->getPath()}";
        echo $cookie->isSecure() ? ', Secure' : '';
        echo $cookie->isSessionCookie() ? ', Session' : ', Persistent';
        echo ")\n";
    }

    // Get cookies for specific URL
    echo "\nCookies matching 'https://example.com/api':\n";
    $apiCookies = $cookieJar->getCookiesForUrl('https://example.com/api');
    foreach ($apiCookies as $cookie) {
        echo "  - {$cookie->getName()}: {$cookie->getValue()}\n";
    }

    echo "\n";
} catch (\Exception $e) {
    echo "Error: {$e->getMessage()}\n\n";
}

// Example 5: Cookie Persistence (Export/Import)
echo "5. Cookie Persistence (Export/Import)\n";
echo str_repeat('-', 50)."\n";

try {
    // Create jar and add cookies
    $jar1 = new CookieJar;
    $jar1->setCookie(new Cookie('persistent', 'data', time() + 86400, 'example.com'));
    $jar1->setCookie(new Cookie('preferences', 'dark_mode=true', time() + 2592000, 'example.com'));

    // Export cookies
    $exported = $jar1->toArray();
    echo 'Exported cookies: '.json_encode($exported, JSON_PRETTY_PRINT)."\n\n";

    // Save to file (simulated)
    $cookieFile = sys_get_temp_dir().'/transport_cookies.json';
    file_put_contents($cookieFile, json_encode($exported));
    echo "Cookies saved to: {$cookieFile}\n";

    // Later... Import cookies
    $jar2 = new CookieJar;
    $imported = json_decode(file_get_contents($cookieFile), true);
    $jar2->fromArray($imported);

    echo "Imported {$jar2->count()} cookies\n";

    // Clean up
    unlink($cookieFile);
    echo "\n";
} catch (\Exception $e) {
    echo "Error: {$e->getMessage()}\n\n";
}

// Example 6: Cookie Filtering and Removal
echo "6. Cookie Filtering and Removal\n";
echo str_repeat('-', 50)."\n";

try {
    $cookieJar = new CookieJar;

    // Add mix of cookies
    $cookieJar->setCookie(new Cookie('keep', 'value', time() + 3600));
    $cookieJar->setCookie(new Cookie('remove', 'value', time() + 3600, 'example.com'));
    $cookieJar->setCookie(new Cookie('expired', 'value', time() - 3600)); // Already expired

    echo "Initial count: {$cookieJar->count()}\n";

    // Remove specific cookie
    $cookieJar->removeCookie('remove', 'example.com');
    echo "After removing 'remove': {$cookieJar->count()}\n";

    // Clear all
    $cookieJar->clear();
    echo "After clearing all: {$cookieJar->count()}\n\n";
} catch (\Exception $e) {
    echo "Error: {$e->getMessage()}\n\n";
}

// Example 7: Web Scraping with Session
echo "7. Web Scraping Simulation\n";
echo str_repeat('-', 50)."\n";

try {
    $cookieJar = new CookieJar;

    // Create transport for scraping
    $scraper = TransportBuilder::make()
        ->withBaseUri('https://httpbin.org')
        ->withCookieJar($cookieJar)
        ->withHeaders([
            'User-Agent' => 'Mozilla/5.0 (compatible; Bot/1.0)',
            'Accept' => 'text/html,application/xhtml+xml',
        ])
        ->build();

    echo "Scraping workflow:\n";

    // 1. Visit homepage (get session)
    echo "  1. Visiting homepage...\n";
    $homepage = $scraper->get('/cookies/set')
        ->withQuery(['session' => 'scraper_'.time()])
        ->send();
    echo "     Session cookies: {$cookieJar->count()}\n";

    // 2. Navigate to different pages (cookies persist)
    echo "  2. Navigating to data page...\n";
    $dataPage = $scraper->get('/cookies')->send();
    echo "     Cookies sent automatically\n";

    // 3. Check collected cookies
    echo "  3. Total cookies collected: {$cookieJar->count()}\n\n";
} catch (\Exception $e) {
    echo "Error: {$e->getMessage()}\n\n";
}

// Example 8: Session Cookie vs Persistent Cookie
echo "8. Session vs Persistent Cookies\n";
echo str_repeat('-', 50)."\n";

try {
    // Without session persistence (default)
    $regularJar = new CookieJar;
    $regularJar->setCookie(new Cookie('session', 'value')); // Session cookie
    $regularJar->setCookie(new Cookie('persistent', 'value', time() + 3600)); // Persistent

    echo "Regular jar (no session persistence):\n";
    echo "  Cookies: {$regularJar->count()}\n";

    // With session persistence
    $persistentJar = CookieJar::withSessionPersistence();
    $persistentJar->setCookie(new Cookie('session', 'value')); // Session cookie
    $persistentJar->setCookie(new Cookie('persistent', 'value', time() + 3600)); // Persistent

    echo "Persistent jar (with session persistence):\n";
    echo "  Cookies: {$persistentJar->count()}\n\n";
} catch (\Exception $e) {
    echo "Error: {$e->getMessage()}\n\n";
}

echo "=== Examples Complete ===\n";
echo "\nNote: Cookie management is transparent and automatic when enabled.\n";
echo "Cookies are matched by domain and path, and secure cookies are only\n";
echo "sent over HTTPS connections as per RFC 6265 specifications.\n";
