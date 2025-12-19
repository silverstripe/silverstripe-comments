<?php

namespace SilverStripe\Comments\Admin;

use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig;
use SilverStripe\Model\List\SS_List;
use SilverStripe\View\HTML;

class CommentsGridField extends GridField
{
    public function __construct($name, $title = null, ?SS_List $dataList = null, ?GridFieldConfig $config = null)
    {
        parent::__construct($name, $title, $dataList, $config);

        $this->addExtraClass('grid-field__filter-buttons');
    }

    /**
     * {@inheritdoc}
     */
    public function newRow($total, $index, $record, $attributes, $content)
    {
        if (!isset($attributes['class'])) {
            $attributes['class'] = '';
        }

        if ($record->IsSpam) {
            $attributes['class'] .= ' spam';
        }

        return HTML::createTag(
            'tr',
            $attributes,
            $content
        );
    }
}
