<?php

/**
 * Handles polymorphic relation for comment list.
 *
 * Uses elements of PolymorphicHasManyList in 3.2
 */
class CommentList extends HasManyList {
	/**
	 * Retrieve the name of the class this relation is filtered by.
	 *
	 * @return string
	 */
	public function getForeignClass() {
		return $this->dataQuery->getQueryParam('Foreign.Class');
	}

	/**
	 * {@inheritdoc}
	 */
	public function __construct($parentClassName) {
		parent::__construct('Comment', 'ParentID');

		$this->dataQuery->setQueryParam('Foreign.Class', $parentClassName);

		$classNames = Convert::raw2sql(ClassInfo::subclassesFor($parentClassName));

		$this->dataQuery->where(sprintf(
			'BaseClass IN (\'%s\')',
			implode('\', \'', $classNames)
		));
	}

	/**
	 * Adds the item to this relation.
	 *
	 * @param Comment $comment
	 */
	public function add($comment) {
		if(is_numeric($comment)) {
			$comment = Comment::get()->byID($comment);
		}

		if(!$comment instanceof Comment) {
			throw new InvalidArgumentException(
				'CommentList::add() expecting a Comment object, or ID value'
			);
		}

		$foreignID = $this->getForeignID();

		if(!$foreignID || is_array($foreignID)) {
			throw new InvalidArgumentException(
				'CommentList::add() can\'t be called until a single foreign ID is set'
			);
		}

		$comment->ParentID = $foreignID;
		$comment->BaseClass = $this->getForeignClass();
		$comment->write();
	}

	/**
	 * Remove a Comment from this relation by clearing the foreign key. Does not actually delete
	 * the comment.
	 *
	 * @param Comment $comment
	 */
	public function remove($comment) {
		if(is_numeric($comment)) {
			$comment = Comment::get()->byID($comment);
		}

		if(!$comment instanceof Comment) {
			throw new InvalidArgumentException(
				'CommentList::remove() expecting a Comment object, or ID',
				E_USER_ERROR
			);
		}

		$foreignClass = $this->getForeignClass();

		$subclasses = ClassInfo::subclassesFor($foreignClass);

		if(!in_array($comment->BaseClass, $subclasses)) {
			return;
		}

		$foreignID = $this->getForeignID();

		if(empty($foreignID) || $foreignID == $comment->ParentID || (is_array($foreignID) && in_array($comment->ParentID, $foreignID))) {
			$comment->ParentID = null;
			$comment->BaseClass = null;
			$comment->write();
		}
	}
}
