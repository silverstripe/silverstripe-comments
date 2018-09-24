<?php

namespace SilverStripe\Comments\Admin;

use SilverStripe\Forms\GridField\GridField;
use SilverStripe\View\HTML;

class CommentsGridField extends GridField
{
    /**
     * {@inheritdoc}
     */
    protected function newRow($total, $index, $record, $attributes, $content)
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
