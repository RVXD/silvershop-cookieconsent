<?php

declare(strict_types=1);

namespace SilverShop\CookieConsent\Extension;

use SilverShop\CookieConsent\Admin\ConsentAdmin;
use SilverStripe\Admin\AdminRootController;
use SilverStripe\Control\Director;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\CompositeField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\HTMLEditor\HTMLEditorField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\TextField;
use SilverStripe\SiteConfig\SiteConfig;

/**
 * Site-wide banner BEHAVIOUR on SiteConfig: whether consent management is on, the
 * banner copy and the three button labels. The cookie declaration (categories +
 * cookies) and the consent log live in {@link ConsentAdmin}, not here.
 *
 * The legacy "hide accept / hide reject / hide manage" knobs are deliberately
 * gone: reject must stay as prominent as accept (EDPB), so all three controls are
 * always shown.
 *
 * @extends Extension<SiteConfig>
 * @property bool $CookieConsentActive
 * @property string $CookieConsentTitle
 * @property string $CookieConsentContent
 * @property string $AcceptAllCookiesTitle
 * @property string $AcceptOnlyNecessaryCookiesTitle
 * @property string $EditCookieSettingsTitle
 */
class SiteConfigExtension extends Extension
{
    private static $db = [
        'CookieConsentActive' => 'Boolean',
        'CookieConsentTitle' => 'Varchar(255)',
        'CookieConsentContent' => 'HTMLText',
        'AcceptAllCookiesTitle' => 'Varchar(255)',
        'AcceptOnlyNecessaryCookiesTitle' => 'Varchar(255)',
        'EditCookieSettingsTitle' => 'Varchar(255)',
    ];

    /**
     * Fluent translatable fields.
     *
     * @var array<int,string>
     */
    private static $translate = [
        'CookieConsentTitle',
        'CookieConsentContent',
        'AcceptAllCookiesTitle',
        'AcceptOnlyNecessaryCookiesTitle',
        'EditCookieSettingsTitle',
    ];

    protected function updateCMSFields(FieldList $fields): void
    {
        $adminLink = Director::absoluteURL(AdminRootController::admin_url() . 'cookie-consent');

        $fields->addFieldsToTab('Root.CookieConsent', [
            CheckboxField::create(
                'CookieConsentActive',
                _t(self::class . '.CookieConsentActive', 'Enable cookie consent')
            )->setDescription(_t(
                self::class . '.CookieConsentActiveDesc',
                'When off, every consent-gated region renders as if granted.'
            )),
            TextField::create(
                'CookieConsentTitle',
                _t(self::class . '.CookieConsentTitle', 'Banner title')
            ),
            HTMLEditorField::create(
                'CookieConsentContent',
                _t(self::class . '.CookieConsentContent', 'Banner text')
            )->setRows(5),
            CompositeField::create([
                HeaderField::create('CookieButtonsHeader', _t(self::class . '.ButtonLabels', 'Button labels')),
                TextField::create(
                    'AcceptAllCookiesTitle',
                    _t(self::class . '.AcceptAllCookiesTitle', 'Accept-all button')
                ),
                TextField::create(
                    'AcceptOnlyNecessaryCookiesTitle',
                    _t(self::class . '.AcceptOnlyNecessaryCookiesTitle', 'Reject / necessary-only button')
                ),
                TextField::create(
                    'EditCookieSettingsTitle',
                    _t(self::class . '.EditCookieSettingsTitle', 'Manage-preferences button')
                ),
                LiteralField::create('RejectParityNote', '<p class="message notice">' . _t(
                    self::class . '.RejectParityNote',
                    'Rejecting is kept as prominent as accepting (EDPB requirement); '
                    . 'the reject and manage controls are always shown.'
                ) . '</p>'),
            ]),
            LiteralField::create('ConsentAdminLink', '<p class="message">' . _t(
                self::class . '.ConsentAdminLink',
                'Manage the cookie declaration (categories and cookies) and the consent log in '
                . '<a href="{link}">Cookie consent</a>.',
                ['link' => $adminLink]
            ) . '</p>'),
        ]);
    }

    /**
     * Seed sensible default copy once (the SiteConfig usually already exists).
     * SS6 invokes extension seeding through the `onRequireDefaultRecords` hook.
     */
    public function onRequireDefaultRecords(): void
    {
        $config = SiteConfig::current_site_config();
        $dirty = false;

        if (empty($config->CookieConsentTitle)) {
            $config->CookieConsentTitle = _t(self::class . '.DefaultTitle', 'Cookies, briefly');
            $dirty = true;
        }
        if (empty($config->CookieConsentContent)) {
            $config->CookieConsentContent = _t(
                self::class . '.DefaultContent',
                '<p>Necessary cookies run the site. With your consent we also measure what works and, where enabled, '
                . 'improve what you see. You can change your mind any time under cookie settings.</p>'
            );
            $dirty = true;
        }

        if ($dirty) {
            $config->write();
        }
    }
}
