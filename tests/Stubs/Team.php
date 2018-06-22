<?php

namespace SilverStripe\Comments\Tests\Stubs;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

class Team extends DataObject implements TestOnly
{
    private static $table_name = 'CommentsTest_Team';

    private static $db = array(
        'Name' => 'Varchar',
        'City' => 'Varchar',
    );

    private static $many_many = [
        'Players' => Player::class,
    ];

    private static $has_many = [
        'Cheerleaders' => Cheerleader::class,
    ];

    private static $searchable_fields = [
        'Name',
        'City',
        'Cheerleaders.Name',
    ];

    public function canView($member = null)
    {
        return true;
    }
}
