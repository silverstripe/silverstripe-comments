<?php

/**
 * Extension to {@link DataObject} to enable tracking comments.
 *
 * @package comments
 */

class CommentsExtension extends DataObjectDecorator {
	
	/**
	 * Adds a relationship between this {@link DataObject} and its
	 * {@link Comment} objects. If the owner class is a sitetree class
	 * it also enables a checkbox allowing comments to be turned off and off
	 * 
	 * @return array
	 */
	function extraStatics() {
		$fields = array();
		
		$relationships = array(
			'has_many' => array(
				'Comments' => 'Comment'
			)
		);

		// if it is attached to the SiteTree then we need to add ProvideComments
		// cannot check $this->owner as this in intialised via call_user_func
		$args = func_get_args();
		
		if($args && ($owner = array_shift($args))) {
			if(ClassInfo::is_subclass_of($owner, 'SiteTree') || $owner == "SiteTree") {
				$fields = array(
					'db' => array(
						'ProvideComments' => 'Boolean'
					)
				);
			}
		}
		
		return array_merge($fields, $relationships);
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
	public function CommentsForm() {
		$interface = new SSViewer('CommentsInterface');
		
		
		// detect whether we comments are enabled. By default if $CommentsForm is included
		// on a {@link DataObject} then it is enabled, however {@link SiteTree} objects can
		// trigger comments on / off via ProvideComments
		$enabled = (!$this->attachedToSiteTree() || $this->owner->ProvideComments) ? true : false;
		
		$form = ($enabled) ? new CommentForm(Controller::curr(), 'CommentsForm', $this->owner) : false;
		
		// if comments are turned off then 
		return $interface->process(new ArrayData(array(
			'CommentsEnabled' => $enabled,
			'AddCommentForm' => $form,
			'Comments' => $this->Comments()
		)));
	}
	
	/**
	 * Returns whether this extension instance is attached to a {@link SiteTree} object
	 *
	 * @return bool
	 */
	public function attachedToSiteTree() {
		return ClassInfo::is_subclass_of($this->ownerBaseClass, 'SiteTree');
	}
	
	/**
	 * @deprecated 1.0 Please use {@link CommentsExtension->CommentsForm()}
	 */
	function PageComments() {
		user_error('$PageComments is deprecated. Please use $CommentsForm', E_USER_WARNING);
		
		return $this->CommentsForm();
	}
}