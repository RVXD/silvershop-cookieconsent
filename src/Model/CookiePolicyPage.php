<?php

declare(strict_types=1);

namespace SilverShop\CookieConsent\Model;

use Page;
use SilverShop\CookieConsent\ConsentState;
use SilverShop\CookieConsent\Control\CookiePolicyPageController;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DB;
use SilverStripe\Versioned\Versioned;

/**
 * Optional default Cookie Policy page: a standalone place to read the cookie
 * declaration and manage preferences without JavaScript. Created on dev/build
 * only when {@link ConsentState}.create_default_pages is enabled (off by
 * default — most sites already have such a page).
 */
class CookiePolicyPage extends Page
{
    private static $table_name = 'SilverShop_CookiePolicyPage';

    private static $controller_name = CookiePolicyPageController::class;

    private static $description = 'Cookie declaration + a no-JavaScript consent management page.';

    private static $defaults = [
        'ShowInMenus' => 0,
    ];

    public function populateDefaults()
    {
        parent::populateDefaults();
        $this->Title = _t(self::class . '.Title', 'Cookie Policy');
        $this->Content = _t(
            self::class . '.Content',
            '<p>This page lists the cookies we use and lets you manage your preferences.</p>'
        );
        return $this;
    }

    public function requireDefaultRecords(): void
    {
        parent::requireDefaultRecords();
        if (!ConsentState::config()->get('create_default_pages')) {
            return;
        }
        if (self::get()->exists()) {
            return;
        }
        $page = self::create();
        $page->write();
        $page->copyVersionToStage(Versioned::DRAFT, Versioned::LIVE);
        $page->flushCache();
        DB::alteration_message('Cookie Policy page created', 'created');
    }

    /**
     * Active categories for the declaration / management form.
     *
     * @return DataList&iterable<CookieCategory>
     */
    public function CookieConsentCategories(): DataList
    {
        return CookieCategory::get()->filter(['Active' => 1]);
    }

    /**
     * The active cookie policy page, or null.
     */
    public static function instance(): ?CookiePolicyPage
    {
        return self::get()->first();
    }

    public function canCreate($member = null, $context = [])
    {
        return self::get()->exists() ? false : parent::canCreate($member, $context);
    }

    public function canDelete($member = null)
    {
        return false;
    }
}
