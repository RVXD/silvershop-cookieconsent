<?php

declare(strict_types=1);

namespace SilverShop\CookieConsent\Model;

use SilverShop\CookieConsent\ConsentState;
use SilverStripe\Control\Controller;
use SilverStripe\Forms\FieldList;
use SilverStripe\i18n\i18n;
use SilverStripe\ORM\DataObject;

/**
 * An immutable record of a single consent decision, for GDPR Art. 7(1)
 * accountability ("be able to demonstrate who consented to what, when and under
 * which policy version").
 *
 * Deliberately pseudonymous: it stores a random consent id (from the consent
 * cookie) — never an IP address or any other PII. Rows cannot be edited or
 * hand-created through the CMS; a retention task prunes them.
 *
 * @property string $ConsentID
 * @property string $Categories
 * @property int $ConsentVersion
 * @property string $Method
 * @property string $Source
 * @property string $Locale
 * @property string $UserAgent
 */
class ConsentRecord extends DataObject
{
    private static $table_name = 'SilverShop_ConsentRecord';

    private static $db = [
        'ConsentID' => 'Varchar(64)',
        'Categories' => 'Varchar(255)',
        'ConsentVersion' => 'Int',
        'Method' => 'Varchar(32)',
        'Source' => 'Varchar(32)',
        'Locale' => 'Varchar(16)',
        'UserAgent' => 'Varchar(255)',
    ];

    private static $indexes = [
        'ConsentID' => true,
        'Created' => true,
    ];

    private static $default_sort = 'Created DESC';

    private static $summary_fields = [
        'Created.Nice' => 'When',
        'ConsentID' => 'Consent ID',
        'Categories' => 'Categories',
        'ConsentVersion' => 'Version',
        'Method' => 'Method',
        'Source' => 'Source',
        'Locale' => 'Locale',
    ];

    private static $singular_name = 'Consent record';

    private static $plural_name = 'Consent records';

    /**
     * Build and persist a record of the current consent state.
     *
     * @param string $source banner|preferences|xhr|policy
     * @return ConsentRecord
     */
    public static function record(string $source = 'banner'): ConsentRecord
    {
        $granted = ConsentState::grantedCategories();
        $active = ConsentState::activeCategories();
        $required = ConsentState::requiredCategories();

        $ua = '';
        if ($controller = Controller::curr()) {
            $ua = (string) $controller->getRequest()->getHeader('User-Agent');
        }

        $record = self::create([
            'ConsentID' => ConsentState::consentId() ?: ConsentState::generateConsentId(),
            'Categories' => implode(',', $granted),
            'ConsentVersion' => ConsentState::version(),
            'Method' => ConsentState::method() ?: ConsentState::deriveMethod($granted, $active, $required),
            'Source' => $source,
            'Locale' => i18n::get_locale(),
            'UserAgent' => mb_substr($ua, 0, 255),
        ]);
        $record->write();

        return $record;
    }

    public function getCMSFields(): FieldList
    {
        // Everything is read-only proof; make the whole form read-only.
        return parent::getCMSFields()->makeReadonly();
    }

    public function canView($member = null)
    {
        return true;
    }

    public function canEdit($member = null)
    {
        return false;
    }

    public function canDelete($member = null)
    {
        return false;
    }

    public function canCreate($member = null, $context = [])
    {
        return false;
    }
}
