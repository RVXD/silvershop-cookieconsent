<?php

declare(strict_types=1);

namespace SilverShop\CookieConsent\Tests;

use SilverShop\CookieConsent\ConsentState;
use SilverStripe\Control\Cookie;
use SilverStripe\Control\Director;
use SilverStripe\Dev\SapphireTest;

/**
 * The cookie-backed state: save / grantAll / rejectAll round-trips, required
 * categories always granted, and needsPrompt against the live cookie.
 */
class ConsentStateCookieTest extends SapphireTest
{
    protected $usesDatabase = true;

    protected function setUp(): void
    {
        parent::setUp();
        Cookie::force_expiry('SilverShopConsent');
    }

    public function testSaveRoundTripAndState(): void
    {
        ConsentState::save(['statistics']);

        $granted = ConsentState::granted();
        $this->assertContains('necessary', $granted, 'required category auto-added');
        $this->assertContains('statistics', $granted);

        $this->assertTrue(ConsentState::has('statistics'));
        $this->assertFalse(ConsentState::has('marketing'));

        $this->assertSame(1, ConsentState::version());
        $this->assertSame('custom', ConsentState::method());
        $this->assertNotEmpty(ConsentState::consentId());
        // Pseudonymous: never an IP.
        $this->assertStringNotContainsString('.', (string) ConsentState::consentId());
    }

    public function testGrantAll(): void
    {
        ConsentState::grantAll();
        $this->assertSame('all', ConsentState::method());
        $this->assertTrue(ConsentState::has('statistics'));
        $this->assertTrue(ConsentState::has('marketing'));
        $this->assertTrue(ConsentState::has('preferences'));
    }

    public function testRejectAll(): void
    {
        ConsentState::rejectAll();
        $this->assertSame('reject', ConsentState::method());
        $this->assertSame(['necessary'], ConsentState::granted());
        $this->assertFalse(ConsentState::has('statistics'));
    }

    public function testRequiredAlwaysGrantedWithoutDecision(): void
    {
        $this->assertFalse(ConsentState::hasDecision());
        $this->assertTrue(ConsentState::has('necessary'));
        $this->assertFalse(ConsentState::has('statistics'));
        $this->assertSame(['necessary'], ConsentState::granted());
    }

    public function testNeedsPromptLiveWhenNoCookie(): void
    {
        $this->assertTrue(ConsentState::needsPrompt());
        ConsentState::grantAll();
        $this->assertFalse(ConsentState::needsPrompt());
    }

    public function testConsentIdPreservedAcrossSaves(): void
    {
        ConsentState::save(['statistics']);
        $first = ConsentState::consentId();
        ConsentState::save(['statistics', 'marketing']);
        $this->assertSame($first, ConsentState::consentId(), 'pseudonymous id is stable across decisions');
    }

    /**
     * The consent cookie must NOT be marked Secure over plain HTTP, or the
     * browser silently drops it and consent never persists (regression guard
     * for the write path: Secure is keyed to the real protocol, not dev/live).
     */
    public function testCookieIsNotSecureOverHttp(): void
    {
        Director::config()->set('alternate_base_url', 'http://consent-test.example');
        $this->assertFalse(
            ConsentState::cookieIsSecure(),
            'consent cookie must not carry Secure over http so it persists on plain-http dev/staging'
        );
    }

    public function testCookieIsSecureOverHttps(): void
    {
        Director::config()->set('alternate_base_url', 'https://consent-test.example');
        $this->assertTrue(
            ConsentState::cookieIsSecure(),
            'consent cookie must carry Secure over https'
        );
    }
}
