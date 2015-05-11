<?php

class CommentsGridFieldAction implements GridField_ColumnProvider, GridField_ActionProvider {
	/**
	 * {@inheritdoc}
	 */
	public function augmentColumns($gridField, &$columns) {
		if(!in_array('Actions', $columns)) {
			$columns[] = 'Actions';
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function getColumnAttributes($gridField, $record, $columnName) {
		return array(
			'class' => 'col-buttons',
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function getColumnMetadata($gridField, $columnName) {
		if($columnName == 'Actions') {
			return array(
				'title' => '',
			);
		}

		return array();
	}

	/**
	 * {@inheritdoc}
	 */
	public function getColumnsHandled($gridField) {
		return array(
			'Actions',
		);
	}

	/**
	 * {@inheritdoc}
	 *
	 * @param Comment $record
	 */
	public function getColumnContent($gridField, $record, $columnName) {
		if(!$record->canEdit()) {
			return '';
		}

		$field = '';

		if(!$record->IsSpam || !$record->Moderated) {
			$field .= GridField_FormAction::create(
				$gridField,
				'CustomAction' . $record->ID,
				'Spam',
				'spam',
				array(
					'RecordID' => $record->ID,
				)
			)->Field();
		}

		if($record->IsSpam || !$record->Moderated) {
			$field .= GridField_FormAction::create(
				$gridField,
				'CustomAction' . $record->ID,
				'Approve',
				'approve',
				array(
					'RecordID' => $record->ID,
				)
			)->Field();
		}

		return $field;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getActions($gridField) {
		return array(
			'spam',
			'approve',
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function handleAction(GridField $gridField, $actionName, $arguments, $data) {
		if($actionName == 'spam') {
			/**
			 * @var Comment $comment
			 */
			$comment = Comment::get()
				->byID($arguments["RecordID"]);

			$comment->markSpam();

			Controller::curr()
				->getResponse()
				->setStatusCode(
					200,
					'Comment marked as spam.'
				);
		}

		if($actionName == 'approve') {
			/**
			 * @var Comment $comment
			 */
			$comment = Comment::get()
				->byID($arguments["RecordID"]);

			$comment->markApproved();

			Controller::curr()
				->getResponse()
				->setStatusCode(
					200,
					'Comment approved.'
				);
		}
	}
}
