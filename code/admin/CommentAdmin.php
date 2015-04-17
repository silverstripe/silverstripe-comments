<?php

/**
 * Comment administration system within the CMS
 *
 * @package comments
 */
class CommentAdmin extends LeftAndMain implements PermissionProvider {

	private static $url_segment = 'comments';

	private static $url_rule = '/$Action';

	private static $menu_title = 'Comments';

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

	public function providePermissions() {
		return array(
			"CMS_ACCESS_CommentAdmin" => array(
				'name' => _t('CommentAdmin.ADMIN_PERMISSION', "Access to 'Comments' section"),
				'category' => _t('Permission.CMS_ACCESS_CATEGORY', 'CMS Access')
			)
		);
	}

	/**
	 * @return Form
	 */
	public function getEditForm($id = null, $fields = null) {
		if(!$id) $id = $this->currentPageID();

		$form = parent::getEditForm($id);
		$record = $this->getRecord($id);

		if($record && !$record->canView()) {
			return Security::permissionFailure($this);
		}

		$commentsConfig = CommentsGridFieldConfig::create();

		$newComments = Comment::get()->filter('Moderated', 0);

		$newGrid = new CommentsGridField(
			'NewComments',
			_t('CommentsAdmin.NewComments', 'New'),
			$newComments,
			$commentsConfig
		);

		$approvedComments = Comment::get()->filter('Moderated', 1)->filter('IsSpam', 0);

		$approvedGrid = new CommentsGridField(
			'ApprovedComments',
			_t('CommentsAdmin.ApprovedComments', 'Approved'),
			$approvedComments,
			$commentsConfig
		);

		$spamComments = Comment::get()->filter('Moderated', 1)->filter('IsSpam', 1);

		$spamGrid = new CommentsGridField(
			'SpamComments',
			_t('CommentsAdmin.SpamComments', 'Spam'),
			$spamComments,
			$commentsConfig
		);

		$newCount = '(' . count($newComments) . ')';
		$approvedCount = '(' . count($approvedComments) . ')';
		$spamCount = '(' . count($spamComments) . ')';

		$fields = new FieldList(
			$root = new TabSet(
				'Root',
				new Tab('NewComments', _t('CommentAdmin.NewComments', 'New') . ' ' . $newCount,
					$newGrid
				),
				new Tab('ApprovedComments', _t('CommentAdmin.ApprovedComments', 'Approved') . ' ' . $approvedCount,
					$approvedGrid
				),
				new Tab('SpamComments', _t('CommentAdmin.SpamComments', 'Spam') . ' ' . $spamCount,
					$spamGrid
				)
			)
		);

		$root->setTemplate('CMSTabSet');

		$actions = new FieldList();

		$form = new Form(
			$this,
			'EditForm',
			$fields,
			$actions
		);

		$form->addExtraClass('cms-edit-form');
		$form->setTemplate($this->getTemplatesWithSuffix('_EditForm'));

		if($form->Fields()->hasTabset()) {
			$form->Fields()->findOrMakeTab('Root')->setTemplate('CMSTabSet');
			$form->addExtraClass('center ss-tabset cms-tabset ' . $this->BaseCSSClasses());
		}

		$this->extend('updateEditForm', $form);

		return $form;
	}
}
