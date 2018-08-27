<?php

namespace SilverStripe\Comments\Admin;

use SilverStripe\Admin\LeftAndMain;
use SilverStripe\Comments\Admin\CommentsGridField;
use SilverStripe\Comments\Model\Comment;
use SilverStripe\Forms\Tab;
use SilverStripe\Forms\TabSet;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Security\PermissionProvider;
use SilverStripe\Security\Security;
use SilverStripe\View\SSViewer;

/**
 * Comment administration system within the CMS
 *
 * @package comments
 */
class CommentAdmin extends LeftAndMain implements PermissionProvider
{
    private static $url_segment = 'comments';

    private static $url_rule = '/$Action';

    private static $menu_title = 'Comments';

    private static $menu_icon_class = 'font-icon-comment';

    private static $allowed_actions = array(
        'approvedmarked',
        'deleteall',
        'deletemarked',
        'hammarked',
        'showtable',
        'spammarked',
        'EditForm',
        'unmoderated'
    );

    private static $required_permission_codes = 'CMS_ACCESS_CommentAdmin';

    public function providePermissions()
    {
        return array(
            "CMS_ACCESS_CommentAdmin" => array(
                'name' => _t(__CLASS__ . '.ADMIN_PERMISSION', "Access to 'Comments' section"),
                'category' => _t('SilverStripe\\Security\\Permission.CMS_ACCESS_CATEGORY', 'CMS Access')
            )
        );
    }

    /**
     * @return Form
     */
    public function getEditForm($id = null, $fields = null)
    {
        if (!$id) {
            $id = $this->currentPageID();
        }

        $form = parent::getEditForm($id);
        $record = $this->getRecord($id);

        if ($record && !$record->canView()) {
            return Security::permissionFailure($this);
        }

        $newComments = Comment::get()->filter('Moderated', 0);

        $newGrid = new CommentsGridField(
            'NewComments',
            '',
            $newComments,
            CommentsGridFieldConfig::create()
        );

        $approvedComments = Comment::get()->filter('Moderated', 1)->filter('IsSpam', 0);

        $approvedGrid = new CommentsGridField(
            'ApprovedComments',
            '',
            $approvedComments,
            CommentsGridFieldConfig::create()
        );

        $spamComments = Comment::get()->filter('Moderated', 1)->filter('IsSpam', 1);

        $spamGrid = new CommentsGridField(
            'SpamComments',
            '',
            $spamComments,
            CommentsGridFieldConfig::create()
        );

        $fields = FieldList::create(
            $root = TabSet::create(
                'Root',
                Tab::create(
                    'NewComments',
                    _t(
                        __CLASS__.'.NewComments',
                        'New ({count})',
                        ['count' => count($newComments)]
                    ),
                    $newGrid
                ),
                Tab::create(
                    'ApprovedComments',
                    _t(
                        __CLASS__.'.ApprovedComments',
                        'Approved ({count})',
                        ['count' => count($approvedComments)]
                    ),
                    $approvedGrid
                ),
                Tab::create(
                    'SpamComments',
                    _t(
                        __CLASS__.'.SpamComments',
                        'Spam ({count})',
                        ['count' => count($spamComments)]
                    ),
                    $spamGrid
                )
            )
        );

        $actions = FieldList::create();

        $form = Form::create(
            $this,
            'EditForm',
            $fields,
            $actions
        );

        $form->addExtraClass('cms-edit-form fill-height');
        $form->setTemplate($this->getTemplatesWithSuffix('_EditForm'));

        if ($form->Fields()->hasTabset()) {
             $form->Fields()->findOrMakeTab('Root')->setTemplate('SilverStripe\\Forms\\CMSTabSet');
            $form->addExtraClass('center ss-tabset cms-tabset ' . $this->BaseCSSClasses());
        }

        $this->extend('updateEditForm', $form);

        return $form;
    }
}
