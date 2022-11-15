<?php

namespace SilverStripe\Comments\Admin;

use SilverStripe\Dev\Deprecation;
use SilverStripe\Comments\Model\Comment;
use SilverStripe\Control\Controller;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridField_ActionProvider;
use SilverStripe\Forms\GridField\GridField_ColumnProvider;
use SilverStripe\Forms\GridField\GridField_FormAction;

/**
 * @deprecated 3.2.0 Use CommentsGridFieldApproveAction CommentsGridFieldSpamAction instead
 */
class CommentsGridFieldAction implements GridField_ColumnProvider, GridField_ActionProvider
{
    /**
     * {@inheritdoc}
     */
    public function __construct()
    {
        Deprecation::notice(
            '3.2.0',
            'Use CommentsGridFieldApproveAction CommentsGridFieldSpamAction instead',
            Deprecation::SCOPE_CLASS
        );
    }

    public function augmentColumns($gridField, &$columns)
    {
        if (!in_array('Actions', $columns ?? [])) {
            $columns[] = 'Actions';
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getColumnAttributes($gridField, $record, $columnName)
    {
        return ['class' => 'col-buttons'];
    }

    /**
     * {@inheritdoc}
     */
    public function getColumnMetadata($gridField, $columnName)
    {
        if ($columnName === 'Actions') {
            return ['title' => ''];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getColumnsHandled($gridField)
    {
        return ['Actions'];
    }

    /**
     * {@inheritdoc}
     */
    public function getColumnContent($gridField, $record, $columnName)
    {
        if (!$record->canEdit()) {
            return;
        }

        $field = '';

        if (!$record->IsSpam || !$record->Moderated) {
            $field .= GridField_FormAction::create(
                $gridField,
                'CustomAction' . $record->ID . 'Spam',
                _t(__CLASS__ . '.SPAM', 'Spam'),
                'spam',
                ['RecordID' => $record->ID]
            )
                ->addExtraClass('btn btn-secondary grid-field__icon-action')
                ->Field();
        }

        if ($record->IsSpam || !$record->Moderated) {
            $field .= GridField_FormAction::create(
                $gridField,
                'CustomAction' . $record->ID . 'Approve',
                _t(__CLASS__ . '.APPROVE', 'Approve'),
                'approve',
                ['RecordID' => $record->ID]
            )
                ->addExtraClass('btn btn-secondary grid-field__icon-action')
                ->Field();
        }

        return $field;
    }

    /**
     * {@inheritdoc}
     */
    public function getActions($gridField)
    {
        return ['spam', 'approve'];
    }

    /**
     * {@inheritdoc}
     */
    public function handleAction(GridField $gridField, $actionName, $arguments, $data)
    {
        if ($actionName === 'spam') {
            /** @var Comment $comment */
            $comment = Comment::get()->byID($arguments['RecordID']);
            $comment->markSpam();

            // output a success message to the user
            Controller::curr()->getResponse()->setStatusCode(
                200,
                _t(__CLASS__ . '.COMMENTMARKEDSPAM', 'Comment marked as spam.')
            );
        }

        if ($actionName === 'approve') {
            /** @var Comment $comment */
            $comment = Comment::get()->byID($arguments['RecordID']);
            $comment->markApproved();

            // output a success message to the user
            Controller::curr()->getResponse()->setStatusCode(
                200,
                _t(__CLASS__ . '.COMMENTAPPROVED', 'Comment approved.')
            );
        }
    }
}
