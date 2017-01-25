<?php

namespace SilverStripe\Comments\Admin;

use Colymba\BulkManager\BulkManager;
use SilverStripe\Comments\Admin\CommentsGridFieldBulkAction\Handler;
use SilverStripe\Core\Convert;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;
use SilverStripe\Forms\GridField\GridFieldDataColumns;

class CommentsGridFieldConfig extends GridFieldConfig_RecordEditor
{
    public function __construct($itemsPerPage = 25)
    {
        parent::__construct($itemsPerPage);

        // $this->addComponent(new GridFieldExportButton());

        $this->addComponent(new CommentsGridFieldAction());

        // Format column
        $columns = $this->getComponentByType(GridFieldDataColumns::class);
        $columns->setFieldFormatting(array(
            'ParentTitle' => function ($value, &$item) {
                return sprintf(
                    '<a href="%s" class="cms-panel-link external-link action" target="_blank">%s</a>',
                    Convert::raw2att($item->Link()),
                    $item->obj('ParentTitle')->forTemplate()
                );
            }
        ));

        // Add bulk option
        $manager = new BulkManager();

        $manager->addBulkAction(
            'spam',
            _t('CommentsGridFieldConfig.SPAM', 'Spam'),
            Handler::class,
            array(
                'isAjax' => true,
                'icon' => 'cross',
                'isDestructive' => false
            )
        );

        $manager->addBulkAction(
            'approve',
            _t('CommentsGridFieldConfig.APPROVE', 'Approve'),
            Handler::class,
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
