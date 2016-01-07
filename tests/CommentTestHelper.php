<?php

class CommentTestHelper {
    /*
    This only works if the last section is not a field group, e.g. a Comments
    field group inside of a Root.Settings tab will not work
     */
    public static function assertFieldsForTab($context, $tabName, $expected, $fields) {
        $tab = $fields->findOrMakeTab($tabName);
        $fields = $tab->FieldList();
        CommentTestHelper::assertFieldNames($context, $expected, $fields);
    }

    public static function assertFieldNames($context, $expected, $fields) {
        $actual = array();
        foreach ($fields as $field) {
            if (get_class($field) == 'FieldGroup') {
                array_push($actual, $field->Name());
            } else {
                array_push($actual, $field->getName());
            }
        }
        $context->assertEquals($expected, $actual);
    }
}
