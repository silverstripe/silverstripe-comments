<?php

namespace SilverStripe\Comments\Tests;

use SilverStripe\Comments\Admin\CommentAdmin;
use SilverStripe\Control\Session;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\i18n\i18n;

class CommentAdminTest extends SapphireTest
{
    protected $usesDatabase = true;

    public function testProvidePermissions()
    {
        $commentAdmin = new CommentAdmin();
        $commentAdmin->getRequest()->setSession(new Session([]));

        i18n::set_locale('fr');
        $this->assertEquals(
            'Accès au CMS',
            $commentAdmin->providePermissions()['CMS_ACCESS_CommentAdmin']['category']
        );

        i18n::set_locale('en');
        $expected = [
            'CMS_ACCESS_CommentAdmin' => [
                'name' => 'Access to \'Comments\' section',
                'category' => 'CMS Access',
            ]
        ];
        $this->assertEquals($expected, $commentAdmin->providePermissions());
    }

    public function testGetEditForm()
    {
        $commentAdmin = new CommentAdmin();
        $commentAdmin->getRequest()->setSession(new Session([]));

        $this->logInWithPermission('CMS_ACCESS_CommentAdmin');
        $form = $commentAdmin->getEditForm();
        $names = $this->getFormFieldNames($form);
        $expected = [
            'NewComments',
            'ApprovedComments',
            'SpamComments',
        ];
        $this->assertEquals($expected, $names);

        $this->logOut();
    }

    private function getFormFieldNames($form)
    {
        $result = [];
        $fields = $form->Fields();
        $tab = $fields->findOrMakeTab('Root');
        $fields = $tab->FieldList();
        foreach ($fields as $field) {
            array_push($result, $field->getName());
        }
        return $result;
    }
}
