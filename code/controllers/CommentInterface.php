<?php

/**
 * Represents an interface for viewing and adding page comments
 * Create one, passing the page discussed to the constructor.  It can then be
 * inserted into a template.
 *
 * @package comments
 */
class CommentInterface extends RequestHandler {
	
	static $url_handlers = array(
		'$Item!' => '$Item',
	);
	static $allowed_actions = array(
		'PostCommentForm',
	);
	
	protected $controller, $methodName, $page;
	

	/**
	 * Create a new page comment interface
	 * @param controller The controller that the interface is used on
	 * @param methodName The method to return this CommentInterface object
	 * @param page The page that we're commenting on
	 */
	function __construct($controller, $methodName, $page) {
		$this->controller = $controller;
		$this->methodName = $methodName;
		$this->page = $page;
		parent::__construct();
	}
	
	function Link() {
		return Controller::join_links($this->controller->Link(), $this->methodName);
	}
	


	/**
	 * if this page comment form requires users to have a
	 * valid permission code in order to post (used to customize the error 
	 * message).
	 * 
	 * @return bool
	 */
	function PostingRequiresPermission() {
		return self::$comments_require_permission;
	}
	
	function Page() {
		return $this->page;
	}
	
	function PostCommentForm() {

	
		// Load the data from Session
		$form->loadDataFrom(array(
			"Name" => Cookie::get("CommentInterface_Name"),
			"Comment" => Cookie::get("CommentInterface_Comment"),
			"CommenterURL" => Cookie::get("CommentInterface_CommenterURL")	
		));
		
		return $form;
	}

	
	function CommentRssLink() {
		return Director::absoluteBaseURL() . "Comment/rss?pageid=" . $this->page->ID;
	}
	
	/**
	 * A link to Comment_Controller.deleteallcomments() which deletes all
	 * comments on a page referenced by the url param pageid
	 */
	function DeleteAllLink() {
		if(Permission::check('CMS_ACCESS_CommentAdmin')) {
			return Director::absoluteBaseURL() . "Comment/deleteallcomments?pageid=" . $this->page->ID;
		}
	}
}