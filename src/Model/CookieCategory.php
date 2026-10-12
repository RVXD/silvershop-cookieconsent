<?php

declare(strict_types=1);

namespace SilverShop\CookieConsent\Model;

use SilverShop\CookieConsent\ConsentState;
use SilverStripe\Control\Director;
use SilverStripe\Core\Environment;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\HTMLEditor\HTMLEditorField;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\HasManyList;

/**
 * A consent category in the standard taxonomy (necessary / preferences /
 * statistics / marketing). Seeded from the {@link ConsentState} `categories`
 * config on dev/build; editors maintain the user-facing copy and the declared
 * cookies, but not the category set itself (that is config-driven).
 *
 * @property string $Code     the taxonomy key ("statistics", ...) — matches the cookie + JS contract
 * @property string $Title
 * @property string $Content
 * @property bool $Active
 * @property bool $Required
 * @property bool $ShowCookies
 * @method HasManyList<Cookie> Cookies()
 */
class CookieCategory extends DataObject
{
    private static $table_name = 'SilverShop_CookieCategory';

    private static $db = [
        'Code' => 'Varchar(50)',
        'Active' => 'Boolean',
        'Required' => 'Boolean',
        'ShowCookies' => 'Boolean(1)',
        'Title' => 'Varchar(255)',
        'Content' => 'HTMLText',
    ];

    private static $indexes = [
        'Code' => true,
    ];

    private static $has_many = [
        'Cookies' => Cookie::class . '.Category',
    ];

    private static $default_sort = 'ID ASC';

    private static $summary_fields = [
        'Code' => 'Key',
        'Title' => 'Title',
        'Active.Nice' => 'Active',
        'Required.Nice' => 'Always on',
    ];

    private static $field_labels = [
        'Code' => 'Key',
        'ShowCookies' => 'Show the cookie declaration table',
    ];

    /**
     * Fluent translatable fields.
     *
     * @var array<int,string>
     */
    private static $translate = [
        'Title',
        'Content',
    ];

    private static $singular_name = 'Cookie category';

    private static $plural_name = 'Cookie categories';

    public function getCMSFields(): FieldList
    {
        $fields = parent::getCMSFields();

        // The category key is config-driven; show it read-only.
        if ($code = $fields->dataFieldByName('Code')) {
            $fields->replaceField('Code', $code->performReadonlyTransformation());
        }

        $fields->dataFieldByName('Required')
            ?->setDescription(_t(
                self::class . '.RequiredDesc',
                'Always-on categories cannot be rejected (necessary cookies). Config-driven.'
            ));
        $content = $fields->dataFieldByName('Content');
        if ($content instanceof HTMLEditorField) {
            $content->setRows(4);
        }

        $this->extend('updateCMSFields', $fields);
        return $fields;
    }

    /**
     * Is this category required (always-on)?
     */
    public function isRequired(): bool
    {
        return (bool) $this->Required || ConsentState::isRequired($this->Code);
    }

    /**
     * Whether this category currently has consent (for the preferences-centre
     * toggle state). Non-throwing.
     */
    public function isGranted(): bool
    {
        return $this->isRequired() || ConsentState::has($this->Code);
    }

    /**
     * Seed the taxonomy + cookie declaration from config.
     */
    public function requireDefaultRecords(): void
    {
        parent::requireDefaultRecords();

        $contentDefaults = [
            'necessary' => '<p>Necessary cookies make the site work: cart, login, checkout and security. Always on.</p>',
            'preferences' => '<p>Preference cookies remember choices such as language, region and layout.</p>',
            'statistics' => '<p>Statistics cookies help us understand how the site is used so we can improve it.</p>',
            'marketing' => '<p>Marketing cookies let us measure our own campaigns; no cross-site tracking.</p>',
        ];

        $categories = ConsentState::config()->get('categories') ?: [];
        foreach ($categories as $code => $settings) {
            $category = self::get()->find('Code', $code);
            if (!$category) {
                $contentDefault = $contentDefaults[$code] ?? ('<p>' . ucfirst((string) $code) . '</p>');
                $category = self::create([
                    'Code' => $code,
                    'Active' => (bool) ($settings['active'] ?? true),
                    'Required' => (bool) ($settings['required'] ?? false),
                    'Title' => _t(self::class . ".{$code}", ucfirst((string) $code)),
                    'Content' => _t(self::class . ".{$code}_Content", $contentDefault),
                ]);
                $category->write();
                DB::alteration_message(sprintf('Cookie category "%s" created', $code), 'created');
            }

            $this->seedCookies($category, $settings['cookies'] ?? []);
            $category->flushCache();
        }
    }

    /**
     * Seed the declared cookies for a category from config.
     *
     * @param array<string,array<int,string>> $providers provider => [cookie names]
     */
    protected function seedCookies(CookieCategory $category, array $providers): void
    {
        foreach ($providers as $provider => $cookies) {
            $providerLabel = $this->providerLabel((string) $provider);
            foreach ($cookies as $cookieName) {
                $existing = Cookie::get()->filter([
                    'Name' => $cookieName,
                    'Provider' => $providerLabel,
                ])->first();
                if ($existing) {
                    continue;
                }
                $cookie = Cookie::create([
                    'Name' => $cookieName,
                    'Provider' => $providerLabel,
                    'Purpose' => _t("SilverShop\\CookieConsent\\Declaration.{$cookieName}_Purpose", $cookieName),
                    'Expiry' => _t("SilverShop\\CookieConsent\\Declaration.{$cookieName}_Expiry", 'Session'),
                ]);
                $category->Cookies()->add($cookie);
                $cookie->flushCache();
                DB::alteration_message(sprintf('Cookie "%s" added to "%s"', $cookieName, $category->Code), 'created');
            }
        }
    }

    /**
     * Resolve a config provider key to a human label ("local" -> the host).
     */
    protected function providerLabel(string $provider): string
    {
        if ($provider === 'local') {
            if (Director::is_cli() && $url = Environment::getEnv('SS_BASE_URL')) {
                return (string) parse_url($url, PHP_URL_HOST);
            }
            return Director::host() ?: 'local';
        }
        return str_replace('_', '.', $provider);
    }

    public function canCreate($member = null, $context = [])
    {
        // Categories are config-driven; editors maintain copy, not the set.
        return false;
    }

    public function canDelete($member = null)
    {
        return false;
    }
}
