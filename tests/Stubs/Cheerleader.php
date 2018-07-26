<?php

namespace SilverStripe\Comments\Tests\Stubs;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

class Cheerleader extends DataObject implements TestOnly
{
    private static $table_name = 'CommentsTest_Cheerleader';

    private static $db = [
        'Name' => 'Varchar',
    ];

    private static $has_one = [
        'Team' => Team::class,
    ];
}
