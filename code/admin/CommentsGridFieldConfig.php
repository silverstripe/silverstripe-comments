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
					Convert::raw2xml($item->Link()),
					Convert::raw2xml($value)
				);
			}
		));

		// Add bulk option
		$manager = new GridFieldBulkManager();

		$manager->addBulkAction(
			'spam', 'Spam', 'CommentsGridFieldBulkAction_Handlers',
			array(
				'isAjax' => true,
				'icon' => 'cross',
				'isDestructive' => false
			)
		);

		$manager->addBulkAction(
			'approve', 'Approve', 'CommentsGridFieldBulkAction_Handlers',
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