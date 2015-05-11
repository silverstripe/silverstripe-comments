<?php

/**
 * Comment administration system within the CMS.
 *
 * @package comments
 */
class CommentAdmin extends LeftAndMain implements PermissionProvider {
	/**
	 * @var string
	 */
	private static $url_segment = 'comments';

	/**
	 * @var string
	 */
	private static $url_rule = '/$Action';

	/**
	 * @var string
	 */
	private static $menu_title = 'Comments';

	/**
	 * @var array
	 */
	private static $allowed_actions = array(
		'approvedmarked',
		'deleteall',
		'deletemarked',
		'hammarked',
		'showtable',
		'spammarked',
		'EditForm',
		'unmoderated',
	);

	/**
	 * {@inheritdoc}
	 */
	public function providePermissions() {
		return array(
			'CMS_ACCESS_CommentAdmin' => array(
				'name' => _t('CommentAdmin.ADMIN_PERMISSION', 'Access to \'Comments\' section'),
				'category' => _t('Permission.CMS_ACCESS_CATEGORY', 'CMS Access'),
			),
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function getEditForm($id = null, $fields = null) {
		if(!$id) {
			$id = $this->currentPageID();
		}

		$record = $this->getRecord($id);

		if($record && !$record->canView()) {
			return Security::permissionFailure($this);
		}

		$commentsConfig = CommentsGridFieldConfig::create();

		$newComments = Comment::get()
			->filter('Moderated', 0);

		$newCommentsGrid = new CommentsGridField(
			'NewComments',
			_t('CommentsAdmin.NewComments', 'New'),
			$newComments,
			$commentsConfig
		);

		$newCommentsCountLabel = sprintf('(%s)', count($newComments));

		$approvedComments = Comment::get()
			->filter('Moderated', 1)
			->filter('IsSpam', 0);

		$approvedCommentsGrid = new CommentsGridField(
			'ApprovedComments',
			_t('CommentsAdmin.ApprovedComments', 'Approved'),
			$approvedComments,
			$commentsConfig
		);

		$approvedCommentsCountLabel = sprintf('(%s)', count($approvedComments));

		$spamComments = Comment::get()
			->filter('Moderated', 1)
			->filter('IsSpam', 1);

		$spamCommentsGrid = new CommentsGridField(
			'SpamComments',
			_t('CommentsAdmin.SpamComments', 'Spam'),
			$spamComments,
			$commentsConfig
		);

		$spamCommentsCountLabel = sprintf('(%s)', count($spamComments));

		$tabSet = new TabSet(
			'Root',
			new Tab(
				'NewComments',
				sprintf(
					'%s %s',
					_t('CommentAdmin.NewComments', 'New'),
					$newCommentsCountLabel
				),
				$newCommentsGrid
			),
			new Tab(
				'ApprovedComments',
				sprintf(
					'%s %s',
					_t('CommentAdmin.ApprovedComments', 'Approved'),
					$approvedCommentsCountLabel
				),
				$approvedCommentsGrid
			),
			new Tab(
				'SpamComments',
				sprintf(
					'%s %s',
					_t('CommentAdmin.SpamComments', 'Spam'),
					$spamCommentsCountLabel
				),
				$spamCommentsGrid
			)
		);

		$tabSet->setTemplate('CMSTabSet');

		$form = new Form(
			$this,
			'EditForm',
			new FieldList($tabSet),
			new FieldList()
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
