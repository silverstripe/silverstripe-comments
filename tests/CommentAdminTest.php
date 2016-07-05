<?php

class CommentAdminTest extends SapphireTest
{

    protected $usesDatabase = true;
    
    public function testProvidePermissions()
    {
        $commentAdmin = new CommentAdmin();
        $locale = i18n::get_locale();

        i18n::set_locale('fr');
        $expected = array(
            'CMS_ACCESS_CommentAdmin' => array(
                # FIXME - this is a bug, missing from lang.yml files
                'name' => 'Access to \'Comments\' section',
                'category' => 'Accès au CMS'
            )
        );
        $this->assertEquals($expected, $commentAdmin->providePermissions());

        i18n::set_locale($locale);
        $expected = array(
            'CMS_ACCESS_CommentAdmin' => array(
                # FIXME - this is a bug, missing from lang.yml files
                'name' => 'Access to \'Comments\' section',
                'category' => 'CMS Access'
            )
        );
        $this->assertEquals($expected, $commentAdmin->providePermissions());
    }

    public function testGetEditForm()
    {
        $commentAdmin = new CommentAdmin();
        $this->logInWithPermission('CMS_ACCESS_CommentAdmin');
        $form = $commentAdmin->getEditForm();
        $names = $this->getFormFieldNames($form);
        $expected = array(
            'NewComments',
            'ApprovedComments',
            'SpamComments'
        );
        $this->assertEquals($expected, $names);

        if ($member = Member::currentUser()) {
            $member->logOut();
        }

        $form = $commentAdmin->getEditForm();
    }

    private function getFormFieldNames($form)
    {
        $result = array();
        $fields = $form->Fields();
        $tab = $fields->findOrMakeTab('Root');
        $fields = $tab->FieldList();
        foreach ($fields as $field) {
            array_push($result, $field->getName());
        }
        return $result;
    }
}
