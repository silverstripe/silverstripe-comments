<?php

/**
 * Extension to {@link DataObject} to enable tracking comments.
 *
 * @package comments
 */

class CommentsExtension extends DataExtension {
	
	/**
	 * Adds a relationship between this {@link DataObject} and its
	 * {@link Comment} objects. If the owner class is a sitetree class
	 * it also enables a checkbox allowing comments to be turned off and off
	 * 
	 * @return array
	 */
	public function extraStatics($class = null, $extension = null) {
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
			if(is_subclass_of($owner, 'SiteTree') || $owner == "SiteTree") {
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
	 * If this extension is applied to a {@link SiteTree} record then
	 * append a Provide Comments checkbox to allow authors to trigger
	 * whether or not to display comments
	 *
	 * @todo Allow customization of other {@link Commenting} configuration
	 *
	 * @param FieldSet
	 */
	public function updateSettingsFields(FieldList $fields) {
		if($this->attachedToSiteTree()) {
			$fields->addFieldToTab('Root.Settings', 
				new CheckboxField('ProvideComments', _t('Comment.ALLOWCOMMENTS', 'Allow Comments'))
			);
		}
	}
	
	/**
	 * Returns a list of all the comments attached to this record.
	 *
	 * @return PaginatedList
	 */
	public function Comments() {
		$order = Commenting::get_config_value($this->ownerBaseClass, 'order_comments_by');
		
		$list = new PaginatedList(Comment::get()->where(sprintf(
			"ParentID = '%s' AND BaseClass = '%s'", $this->owner->ID, $this->ownerBaseClass
		))->sort($order));

		$list->setPageLength(Commenting::get_config_value(
			$this->ownerBaseClass, 'comments_per_page'
		));

		$list->setPaginationGetVar("commentsstart". $this->owner->ID);

		return $list;
	}
	
	
	/**
	 * Comments interface for the front end. Includes the CommentAddForm and the composition
	 * of the comments display. 
	 * 
	 * To customize the html see templates/CommentInterface.ss or extend this function with
	 * your own extension.
	 *
	 * @todo Cleanup the passing of all this configuration based functionality
	 *
	 * @see docs/en/Extending
	 */
	public function CommentsForm() {
		$interface = new SSViewer('CommentsInterface');
		
		// detect whether we comments are enabled. By default if $CommentsForm is included
		// on a {@link DataObject} then it is enabled, however {@link SiteTree} objects can
		// trigger comments on / off via ProvideComments
		$enabled = (!$this->attachedToSiteTree() || $this->owner->ProvideComments) ? true : false;
		
		// do not include the comments on pages which don't have id's such as security pages
		if($this->owner->ID < 0) return false;
		
		$controller = new CommentingController();		
		$controller->setOwnerRecord($this->owner);
		$controller->setBaseClass($this->ownerBaseClass);
		$controller->setOwnerController(Controller::curr());
		
		$form = ($enabled) ? $controller->CommentsForm() : false;
		
		// a little bit all over the show but to ensure a slightly easier upgrade for users
		// return back the same variables as previously done in comments
		return $interface->process(new ArrayData(array(
			'CommentHolderID' 			=> Commenting::get_config_value($this->ownerBaseClass, 'comments_holder_id'),
			'PostingRequiresPermission' => Commenting::get_config_value($this->ownerBaseClass, 'required_permission'),
			'CanPost' 					=> Commenting::can_member_post($this->ownerBaseClass),
			'RssLink'					=> "CommentingController/rss",
			'RssLinkPage'				=> "CommentingController/rss/". $this->ownerBaseClass . '/'.$this->owner->ID,
			'CommentsEnabled' 			=> $enabled,
			'AddCommentForm'			=> $form,
			'Comments'					=> $this->Comments()
		)));
	}
	
	/**
	 * Returns whether this extension instance is attached to a {@link SiteTree} object
	 *
	 * @return bool
	 */
	public function attachedToSiteTree() {
		$class = $this->ownerBaseClass;
		
		return (is_subclass_of($class, 'SiteTree')) || ($class == 'SiteTree');
	}
	
	/**
	 * @deprecated 1.0 Please use {@link CommentsExtension->CommentsForm()}
	 */
	public function PageComments() {
		// This method is very commonly used, don't throw a warning just yet
		//user_error('$PageComments is deprecated. Please use $CommentsForm', E_USER_WARNING);
		
		return $this->CommentsForm();
	}
}