<?php

class CommentsGridFieldConfig extends GridFieldConfig_RecordEditor {
	public function __construct($itemsPerPage = 25) {
		parent::__construct($itemsPerPage);

		// $this->addComponent(new GridFieldExportButton());

		$this->addComponent(new CommentsGridFieldAction());

		// Format column
		$columns = $this->getComponentByType('GridFieldDataColumns');
		$columns->setFieldFormatting(array(
			'ParentTitle' => function($value, &$item) {
				return sprintf(
					'<a href="%s" class="cms-panel-link external-link action" target="_blank">%s</a>',
					Convert::raw2att($item->Link()),
					$item->obj('ParentTitle')->forTemplate()
				);
			}
		));

		// Add bulk option
		$manager = new GridFieldBulkManager();

		$manager->addBulkAction(
			'markAsSpam', 'Mark as spam', 'CommentsGridFieldBulkAction_Handlers',
			array(
				'isAjax' => true,
				'icon' => 'cross',
				'isDestructive' => false
			)
		);

		$manager->addBulkAction(
			'markAsNotSpam', 'Mark as not spam', 'CommentsGridFieldBulkAction_Handlers',
			array(
				'isAjax' => true,
				'icon' => 'cross',
				'isDestructive' => false
			)
		);

		$manager->removeBulkAction('bulkEdit');
		$manager->removeBulkAction('unLink');

		$this->addComponent($manager);
	}
}