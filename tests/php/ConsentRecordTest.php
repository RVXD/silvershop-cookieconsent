<?php

declare(strict_types=1);

namespace SilverShop\CookieConsent\Tests;

use SilverShop\CookieConsent\ConsentState;
use SilverShop\CookieConsent\Model\ConsentRecord;
use SilverStripe\Control\Cookie;
use SilverStripe\Dev\SapphireTest;

class ConsentRecordTest extends SapphireTest
{
    protected $usesDatabase = true;

    protected function setUp(): void
    {
        parent::setUp();
        Cookie::force_expiry('SilverShopConsent');
    }

    public function testRecordWritesImmutableRow(): void
    {
        ConsentState::save(['statistics']);

        $record = ConsentRecord::record('banner');

        $this->assertTrue($record->isInDB());
        $this->assertSame('banner', $record->Source);
        $this->assertStringContainsString('statistics', $record->Categories);
        $this->assertSame('custom', $record->Method);
        $this->assertSame(1, (int) $record->ConsentVersion);
        $this->assertNotEmpty($record->ConsentID);
        // Pseudonymous: never store an IP.
        $this->assertStringNotContainsString('.', (string) $record->ConsentID);
    }

    public function testImmutability(): void
    {
        $record = ConsentRecord::record('banner');
        $this->assertFalse($record->canEdit());
        $this->assertFalse($record->canDelete());
        $this->assertFalse($record->canCreate());
        $this->assertTrue($record->canView());
    }
}
