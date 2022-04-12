<?php

namespace SilverStripe\Comments\Admin;

use SilverStripe\Comments\Model\Comment;
use SilverStripe\Control\Controller;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridField_ActionMenuItem;
use SilverStripe\Forms\GridField\GridField_ActionProvider;
use SilverStripe\Forms\GridField\GridField_ColumnProvider;
use SilverStripe\Forms\GridField\GridField_FormAction;

class CommentsGridFieldApproveAction implements
    GridField_ColumnProvider,
    GridField_ActionProvider,
    GridField_ActionMenuItem
{
    /**
     * {@inheritdoc}
     */
    public function augmentColumns($gridField, &$columns)
    {
        if (!in_array('Actions', $columns ?? [])) {
            $columns[] = 'Actions';
        }
    }

    public function getTitle($gridField, $record, $columnName)
    {
        return _t(__CLASS__ . '.APPROVE', 'Approve');
    }

    public function getExtraData($gridField, $record, $columnName)
    {

        $field = $this->getApproveAction($gridField, $record, $columnName);

        if ($field) {
            return $field->getAttributes();
        }

        return null;
    }
    public function getGroup($gridField, $record, $columnName)
    {
        $field = $this->getApproveAction($gridField, $record, $columnName);

        return $field ? GridField_ActionMenuItem::DEFAULT_GROUP: null;
    }

    /**
     * {@inheritdoc}
     */
    public function getColumnAttributes($gridField, $record, $columnName)
    {
        return ['class' => 'col-buttons grid-field__col-compact'];
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

        $field = $this->getApproveAction($gridField, $record, $columnName);

        return $field ? $field->Field() : null;
    }

    /**
     * Returns the FormAction object, used by other methods to get properties
     *
     * @return GridField_FormAction|null
     */
    public function getApproveAction($gridField, $record, $columnName)
    {
        $field = GridField_FormAction::create(
            $gridField,
            'CustomAction' . $record->ID . 'Approve',
            _t(__CLASS__ . '.APPROVE', 'Approve'),
            'approve',
            ['RecordID' => $record->ID]
        )
            ->addExtraClass(implode(' ', [
                'btn',
                'btn-secondary',
                'grid-field__icon-action',
                'action-menu--handled',
                'font-icon-check-mark',
            ]))
            ->setAttribute('classNames', 'font-icon-check-mark');

        return ($record->IsSpam || !$record->Moderated) ? $field : null;
    }

    /**
     * {@inheritdoc}
     */
    public function getActions($gridField)
    {
        return ['approve'];
    }

    /**
     * {@inheritdoc}
     */
    public function handleAction(GridField $gridField, $actionName, $arguments, $data)
    {
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
