<?php

class CommentsGridFieldAction implements GridField_ColumnProvider, GridField_ActionProvider
{
    /**
     * {@inheritdoc}
     */
    public function augmentColumns($gridField, &$columns)
    {
        if (!in_array('Actions', $columns)) {
            $columns[] = 'Actions';
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getColumnAttributes($gridField, $record, $columnName)
    {
        return array('class' => 'col-buttons');
    }

    /**
     * {@inheritdoc}
     */
    public function getColumnMetadata($gridField, $columnName)
    {
        if ($columnName == 'Actions') {
            return array('title' => '');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getColumnsHandled($gridField)
    {
        return array('Actions');
    }

    /**
     * {@inheritdoc}
     */
    public function getColumnContent($gridField, $record, $columnName)
    {
        if (!$record->canEdit()) {
            return;
        }

        $field = "";

        if (!$record->IsSpam || !$record->Moderated) {
            $field .= GridField_FormAction::create(
                $gridField,
                'CustomAction' . $record->ID . 'Spam',
                'Spam',
                'spam',
                array('RecordID' => $record->ID)
            )->Field();
        }

        if ($record->IsSpam || !$record->Moderated) {
            $field .= GridField_FormAction::create(
                $gridField,
                'CustomAction' . $record->ID . 'Approve',
                'Approve',
                'approve',
                array('RecordID' => $record->ID)
            )->Field();
        }

        return $field;
    }

    /**
     * {@inheritdoc}
     */
    public function getActions($gridField)
    {
        return array('spam', 'approve');
    }

    /**
     * {@inheritdoc}
     */
    public function handleAction(GridField $gridField, $actionName, $arguments, $data)
    {
        if ($actionName == 'spam') {
            $comment = Comment::get()->byID($arguments["RecordID"]);
            $comment->markSpam();

            // output a success message to the user
            Controller::curr()->getResponse()->setStatusCode(
                200,
                'Comment marked as spam.'
            );
        }

        if ($actionName == 'approve') {
            $comment = Comment::get()->byID($arguments["RecordID"]);
            $comment->markApproved();

            // output a success message to the user
            Controller::curr()->getResponse()->setStatusCode(
                200,
                'Comment approved.'
            );
        }
    }
}
