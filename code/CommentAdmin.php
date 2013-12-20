<?php

/**
 * Comment administration system within the CMS
 *
 * @package comments
 */
class CommentAdmin extends LeftAndMain {

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

		$commentsConfig = GridFieldConfig::create()->addComponents(
			new GridFieldFilterHeader(),
			new GridFieldDataColumns(),
			new GridFieldSortableHeader(),
			new GridFieldPaginator(25),
			new GridFieldDeleteAction(),
			new GridFieldDetailForm(),
			new GridFieldExportButton(),
			new GridFieldEditButton(),
			new GridFieldDetailForm(),
			$manager = new GridFieldBulkManager()
		);

		$manager->addBulkAction(
			'markAsSpam', 'Mark as spam', 'CommentsGridFieldBulkAction_MarkAsSpam', 
			array(
				'isAjax' => true,
				'icon' => 'delete',
				'isDestructive' => true 
			)
		);

		$needs = new GridField(
			'Comments', 
			_t('CommentsAdmin.NeedsModeration', 'Needs Moderation'), 
			Comment::get()->filter('Moderated',0),
			$commentsConfig
		);

		$moderated = new GridField(
			'CommentsModerated', 
			_t('CommentsAdmin.CommentsModerated'),
			Comment::get()->filter('Moderated',1),
			$commentsConfig
		);

		$fields = new FieldList(
			$root = new TabSet(
				'Root',
				new Tab('NeedsModeration', _t('CommentAdmin.NeedsModeration', 'Needs Moderation'), 
					$needs
				),
				new Tab('Comments', _t('CommentAdmin.Moderated', 'Moderated'),
					$moderated
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
