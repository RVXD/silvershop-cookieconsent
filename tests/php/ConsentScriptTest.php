<?php

declare(strict_types=1);

namespace SilverShop\CookieConsent\Tests;

use SilverShop\CookieConsent\View\ConsentScript;
use SilverStripe\Dev\SapphireTest;

class ConsentScriptTest extends SapphireTest
{
    protected $usesDatabase = false;

    public function testInlineEmitsInertScript(): void
    {
        $html = (string) ConsentScript::inline('console.log(1)', 'statistics');
        $this->assertStringContainsString('type="text/plain"', $html);
        $this->assertStringContainsString('data-consent-category="statistics"', $html);
        $this->assertStringContainsString('console.log(1)', $html);
    }

    public function testSrcParksUrlInDataSrc(): void
    {
        $html = (string) ConsentScript::src('https://example.com/t.js', 'marketing', ['async' => true, 'id' => 'x']);
        $this->assertStringContainsString('type="text/plain"', $html);
        $this->assertStringContainsString('data-consent-category="marketing"', $html);
        // The real URL must NOT be in a src attribute (preload scanners would fetch it).
        $this->assertStringContainsString('data-src="https://example.com/t.js"', $html);
        $this->assertStringNotContainsString(' src="https://example.com/t.js"', $html);
        $this->assertStringContainsString(' async', $html);
        $this->assertStringContainsString('id="x"', $html);
    }

    public function testSanitiseCategory(): void
    {
        $this->assertSame('statistics', ConsentScript::sanitiseCategory('stati$stics'));
        $this->assertSame('statistics', ConsentScript::sanitiseCategory(''));
        $this->assertSame('marketing', ConsentScript::sanitiseCategory('marketing'));
    }
}
