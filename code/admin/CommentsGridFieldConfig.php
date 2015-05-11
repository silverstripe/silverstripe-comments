<?php

/**
 * @method static CommentsGridFieldConfig create()
 */
class CommentsGridFieldConfig extends GridFieldConfig_RecordEditor {
	/**
	 * {@inheritdoc}
	 */
	public function __construct($itemsPerPage = 25) {
		parent::__construct($itemsPerPage);

		$this->addComponent(new CommentsGridFieldAction());

		/**
		 * @var GridFieldDataColumns $columns
		 */
		$columns = $this->getComponentByType('GridFieldDataColumns');

		$columns->setFieldFormatting(array(
			'ParentTitle' => function ($value, &$item) {
				return sprintf(
					'<a href="%s" class="cms-panel-link external-link action" target="_blank">%s</a>',
					Convert::raw2att($item->Link()),
					$item->obj('ParentTitle')->forTemplate()
				);
			}
		));

		$manager = new GridFieldBulkManager();

		$manager->addBulkAction(
			'spam',
			'Spam',
			'CommentsGridFieldBulkAction_Handlers',
			array(
				'isAjax' => true,
				'icon' => 'cross',
				'isDestructive' => false,
			)
		);

		$manager->addBulkAction(
			'approve',
			'Approve',
			'CommentsGridFieldBulkAction_Handlers',
			array(
				'isAjax' => true,
				'icon' => 'cross',
				'isDestructive' => false,
			)
		);

		$manager->removeBulkAction('bulkEdit');
		$manager->removeBulkAction('unLink');

		$this->addComponent($manager);
	}
}
