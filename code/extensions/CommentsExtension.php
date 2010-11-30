<?php

/**
 * Extension to {@link DataObject} to enable tracking comments.
 *
 * @package comments
 */

class CommentsExtension extends DataObjectDecorator {
	
	/**
	 * Adds a relationship between this {@link DataObject} and its
	 * {@link Comment} objects
	 * 
	 * @return array
	 */
	function extraStatics() {
		return array(
			'has_many' => array(
				'Comments' => 'Comment'
			)
		);
	}
	
	/**
	 * @var int Number of comments to show per page
	 */
	private static $comments_per_page = 10;
	
	/**
	 * Set the number of comments displayed per page
	 *
	 * @param int Number of comments to show per page
	 */
	public static function set_comments_per_page($num) {
		self::$comments_per_page = (int)$num;
	}
	
	/**
	 * Returns the number of comments per page
	 *
	 * @return int
	 */
	public static function comments_per_page() {
		return self::$comments_per_page;
	}
	
	/**
	 * Returns a list of all the comments attached to this record.
	 *
	 * @todo pagination
	 *
	 * @return DataObjectSet
	 */
	function Comments() {
		return DataObject::get('Comment', "\"ParentID\" = '". $this->owner->ID ."' AND \"ParentClass\" = '". $this->ownerBaseClass ."'");
	}
	
	/**
	 * Comments interface for the front end. Includes the CommentAddForm and the composition
	 * of the comments display. 
	 * 
	 * To customize the html see templates/CommentInterface.ss or extend this function with
	 * your own extension.
	 *
	 * @see docs/en/Extending
	 */
	function CommentsForm() {
		$interface = new SSViewer('CommentsInterface');
		
		return $interface->process(new ArrayData(array(
			'Comments' => $this->Comments()
		)));
	}
	
	/**
	 * @deprecated 1.0 Please use {@link CommentsExtension->CommentsForm()}
	 */
	function PageComments() {
		user_error('$PageComments is deprecated. Please use $CommentsForm');
		
		return $this->CommentsForm();
	}
}