<?php

declare(strict_types=1);

namespace SilverShop\CookieConsent\Model;

use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\DataObject;

/**
 * A single declared cookie, shown in the cookie declaration table and the
 * preferences centre. Belongs to a {@link CookieCategory}.
 *
 * (Note: this is the declaration model, distinct from the framework's
 * SilverStripe\Control\Cookie runtime helper — they never meet in one file.)
 *
 * @property string $Name
 * @property string $Provider
 * @property string $Purpose
 * @property string $Expiry
 * @property string $Type
 * @property int $Sort
 * @property int $CategoryID
 * @method CookieCategory Category()
 */
class Cookie extends DataObject
{
    private static $table_name = 'SilverShop_Cookie';

    private static $db = [
        'Name' => 'Varchar(255)',
        'Provider' => 'Varchar(255)',
        'Purpose' => 'Varchar(255)',
        'Expiry' => 'Varchar(255)',
        'Type' => 'Varchar(50)',
        'Sort' => 'Int',
    ];

    private static $has_one = [
        'Category' => CookieCategory::class,
    ];

    private static $default_sort = 'Sort ASC, Name ASC';

    private static $summary_fields = [
        'Name' => 'Name',
        'Provider' => 'Placed by',
        'Purpose' => 'Purpose',
        'Expiry' => 'Expiry',
    ];

    /**
     * Fluent translatable fields.
     *
     * @var array<int,string>
     */
    private static $translate = [
        'Purpose',
        'Expiry',
    ];

    private static $singular_name = 'Cookie';

    private static $plural_name = 'Cookies';

    public function getCMSFields(): FieldList
    {
        $fields = parent::getCMSFields();
        $fields->removeByName(['Sort', 'CategoryID']);
        $this->extend('updateCMSFields', $fields);
        return $fields;
    }
}
