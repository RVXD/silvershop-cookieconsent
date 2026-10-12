<?php

declare(strict_types=1);

namespace SilverShop\CookieConsent\Admin;

use SilverShop\CookieConsent\Model\Cookie;
use SilverShop\CookieConsent\Model\ConsentRecord;
use SilverShop\CookieConsent\Model\CookieCategory;
use SilverStripe\Admin\ModelAdmin;
use SilverStripe\Forms\GridField\GridFieldConfig;
use Symbiote\GridFieldExtensions\GridFieldOrderableRows;

/**
 * Dedicated CMS section for the cookie declaration (categories + cookies) and the
 * immutable consent log. Banner behaviour stays on Settings (SiteConfig).
 */
class ConsentAdmin extends ModelAdmin
{
    private static string $url_segment = 'cookie-consent';

    private static string $menu_title = 'Cookie consent';

    private static string $menu_icon_class = 'font-icon-block';

    private static array $managed_models = [
        CookieCategory::class,
        Cookie::class,
        ConsentRecord::class,
    ];

    protected function getGridFieldConfig(): GridFieldConfig
    {
        $config = parent::getGridFieldConfig();

        // Drag-and-drop ordering of the cookie declaration, only when the
        // component is available (soft dependency).
        if ($this->getModelClass() === Cookie::class && class_exists(GridFieldOrderableRows::class)) {
            $config->addComponent(GridFieldOrderableRows::create('Sort'));
        }

        return $config;
    }
}
