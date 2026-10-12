<?php

declare(strict_types=1);

namespace SilverShop\CookieConsent\Tests;

use SilverShop\CookieConsent\ConsentState;
use SilverStripe\Dev\SapphireTest;

/**
 * Pure (cookie-free) logic: re-consent decisions, method derivation, the
 * encode/decode round-trip, the taxonomy helpers and the Consent Mode map.
 */
class ConsentStateTest extends SapphireTest
{
    protected $usesDatabase = false;

    public function testNeedsPromptWhenNoDecision(): void
    {
        $this->assertTrue(ConsentState::evaluateNeedsPrompt(null, 1, 183, 365, false, 1000));
    }

    public function testNeedsPromptWhenDecisionButNoState(): void
    {
        // A decision exists but the payload is unreadable: re-prompt once.
        $this->assertTrue(ConsentState::evaluateNeedsPrompt(null, 1, 183, 365, true, 1000));
    }

    public function testNoPromptWhenFresh(): void
    {
        $now = 1_000_000_000;
        $state = ['v' => 1, 't' => $now - 10, 'm' => 'all', 'i' => 'x', 'c' => ['necessary']];
        $this->assertFalse(ConsentState::evaluateNeedsPrompt($state, 1, 183, 365, true, $now));
    }

    public function testPromptWhenVersionBehind(): void
    {
        $now = 1_000_000_000;
        $state = ['v' => 1, 't' => $now, 'm' => 'all', 'i' => 'x'];
        $this->assertTrue(ConsentState::evaluateNeedsPrompt($state, 2, 183, 365, true, $now));
    }

    public function testPromptWhenExpired(): void
    {
        $now = 1_000_000_000;
        $state = ['v' => 1, 't' => $now - (200 * 86400), 'm' => 'all', 'i' => 'x'];
        $this->assertTrue(ConsentState::evaluateNeedsPrompt($state, 1, 183, 365, true, $now));
    }

    public function testRejectSuppressedWithinWindow(): void
    {
        $now = 1_000_000_000;
        // 200 days old: past the 183-day lifetime, but inside the 365-day
        // post-reject suppression window -> do not re-prompt.
        $state = ['v' => 1, 't' => $now - (200 * 86400), 'm' => 'reject', 'i' => 'x'];
        $this->assertFalse(ConsentState::evaluateNeedsPrompt($state, 1, 183, 365, true, $now));
    }

    public function testRejectRePromptsBeyondWindow(): void
    {
        $now = 1_000_000_000;
        $state = ['v' => 1, 't' => $now - (400 * 86400), 'm' => 'reject', 'i' => 'x'];
        $this->assertTrue(ConsentState::evaluateNeedsPrompt($state, 1, 183, 365, true, $now));
    }

    public function testDeriveMethod(): void
    {
        $all = ['necessary', 'preferences', 'statistics', 'marketing'];
        $required = ['necessary'];
        $this->assertSame('all', ConsentState::deriveMethod($all, $all, $required));
        $this->assertSame('reject', ConsentState::deriveMethod(['necessary'], $all, $required));
        $this->assertSame('custom', ConsentState::deriveMethod(['necessary', 'statistics'], $all, $required));
        // order-independent
        $this->assertSame('all', ConsentState::deriveMethod(
            ['marketing', 'necessary', 'statistics', 'preferences'],
            $all,
            $required
        ));
    }

    public function testDecodeAndEncode(): void
    {
        $this->assertNull(ConsentState::decode(null));
        $this->assertNull(ConsentState::decode(''));
        $this->assertNull(ConsentState::decode('not json'));

        $payload = ['v' => 2, 't' => 123, 'm' => 'all', 'i' => 'abc', 'c' => ['necessary', 'statistics']];
        $decoded = ConsentState::decode(ConsentState::encode($payload));
        $this->assertSame(2, $decoded['v']);
        $this->assertSame('all', $decoded['m']);
        $this->assertSame(['necessary', 'statistics'], $decoded['c']);
    }

    public function testTaxonomyHelpers(): void
    {
        $this->assertSame(
            ['necessary', 'preferences', 'statistics', 'marketing'],
            ConsentState::configuredCategories()
        );
        $this->assertContains('statistics', ConsentState::activeCategories());
        $this->assertSame(['necessary'], ConsentState::requiredCategories());
        $this->assertTrue(ConsentState::isRequired('necessary'));
        $this->assertFalse(ConsentState::isRequired('statistics'));
        $this->assertTrue(ConsentState::isActive('marketing'));
        $this->assertFalse(ConsentState::isActive('nonsense'));
    }

    public function testConsentModeMapFromConfig(): void
    {
        $map = ConsentState::consentModeMap();
        $this->assertArrayHasKey('statistics', $map);
        $this->assertContains('analytics_storage', $map['statistics']);
        $this->assertArrayHasKey('marketing', $map);
        $this->assertContains('ad_user_data', $map['marketing']);

        $signals = ConsentState::allConsentModeSignals();
        $this->assertContains('analytics_storage', $signals);
        $this->assertContains('ad_storage', $signals);
        $this->assertContains('ad_personalization', $signals);
    }
}
