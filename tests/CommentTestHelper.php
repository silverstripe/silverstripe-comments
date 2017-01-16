<?php

namespace SilverStripe\Comments\Tests;

use SilverStripe\Dev\TestOnly;
use SilverStripe\Forms\FieldGroup;

class CommentTestHelper implements TestOnly
{
    /**
     * This only works if the last section is not a field group, e.g. a Comments
     * field group inside of a Root.Settings tab will not work
     */
    public static function assertFieldsForTab($context, $tabName, $expected, $fields)
    {
        $tab = $fields->findOrMakeTab($tabName);
        $fields = $tab->FieldList();
        self::assertFieldNames($context, $expected, $fields);
    }

    public static function assertFieldNames($context, $expected, $fields)
    {
        $actual = array();
        foreach ($fields as $field) {
            array_push($actual, $field->getName());
        }
        $context->assertEquals($expected, $actual);
    }
}
