<?php

namespace SilverStripe\Comments\Admin;

use Colymba\BulkManager\BulkManager;
use SilverStripe\Comments\Admin\CommentsGridFieldBulkAction\ApproveHandler;
use SilverStripe\Comments\Admin\CommentsGridFieldBulkAction\SpamHandler;
use SilverStripe\Core\Convert;
use SilverStripe\Forms\GridField\GridField_ActionMenu;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;
use SilverStripe\Forms\GridField\GridFieldDataColumns;

class CommentsGridFieldConfig extends GridFieldConfig_RecordEditor
{
    public function __construct($itemsPerPage = 25)
    {
        parent::__construct($itemsPerPage);

        // $this->addComponent(new GridFieldExportButton());

        $this->addComponents([
            new CommentsGridFieldSpamAction(),
            new CommentsGridFieldApproveAction(),
        ]);

        // Format column
        /** @var GridFieldDataColumns $columns */
        $columns = $this->getComponentByType(GridFieldDataColumns::class);
        $columns->setFieldFormatting([
            'Parent.Title' => function ($value, &$item) {
                if ($link = $item->Link()) {
                    return sprintf(
                        '<a href="%s" class="cms-panel-link external-link action" target="_blank">%s</a>',
                        Convert::raw2att($link),
                        $item->obj('ParentTitle')->forTemplate()
                    );
                } else {
                    return $item->obj('ParentTitle')->forTemplate();
                }
            }
        ]);

        // Add bulk option
        $manager = BulkManager::create(null, false);

        $spamAction = SpamHandler::create()->setLabel(_t(__CLASS__ . '.SPAM', 'Spam'));
        $approveAction = ApproveHandler::create()->setLabel(_t(__CLASS__ . '.APPROVE', 'Approve'));

        $manager
            ->addBulkAction($spamAction)
            ->addBulkAction($approveAction);

        $this->addComponent($manager);
    }
}
