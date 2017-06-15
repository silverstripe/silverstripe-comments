<?php

class CommentAdminTest extends SapphireTest
{
    protected $usesDatabase = true;

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
