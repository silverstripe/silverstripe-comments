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
	 * If this is true, you must be logged in to post a comment 
	 * (and therefore, you don't need to specify a 'Your name' field unless 
	 * your name is blank)
	 * 
	 * @var bool
	 */
	private static $comments_require_login = false;
	
	/**
	 * If this is a valid permission code, you must be logged in 
	 * and have the appropriate permission code on your account before you can 
	 * post a comment.
	 * 
	 * @var string 
	 */
	private static $comments_require_permission = "";
	
	/**
	 * If this is true it will include the javascript for AJAX 
	 * commenting. If it is set to false then it will not load
	 * the files required and it will fall back
	 * 
	 * @var bool
	 */
	private static $use_ajax_commenting = true;
	
	/**
	 * If this is true then we should show the existing comments on 
	 * the page even when we have disabled the comment form. 
	 *
	 * If this is false the form + existing comments will be hidden
	 * 
	 * @var bool
	 */
	private static $show_comments_when_disabled = true;
	
	/**
	 * Define how you want to order page comments by. By default order by newest
	 * to oldest. 
	 * 
	 * @var String - used as $orderby in DB query
	 * @since 2.4 
	 */
	static $order_comments_by = "\"Created\" DESC";
	
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
	 * See {@link CommentInterface::$comments_require_login}
	 *
	 * @param boolean state The new state of this static field
	 */
	static function set_comments_require_login($state) {
		self::$comments_require_login = (boolean) $state;
	}
	
	/**
	 * See {@link CommentInterface::$comments_require_permission}
	 *
	 * @param string permission The permission to check against.
	 */
	static function set_comments_require_permission($permission) {
		self::$comments_require_permission = $permission;
	}
	
	/**
	 * See {@link CommentInterface::$show_comments_when_disabled}
	 * 
	 * @param bool - show / hide the existing comments when disabled
	 */
	static function set_show_comments_when_disabled($state) {
		self::$show_comments_when_disabled = $state;
	}
	
	/**
	 * See {@link CommentInterface::$order_comments_by}
	 *
	 * @param String
	 */
	static function set_order_comments_by($order) {
		self::$order_comments_by = $order;
	}
	
	/**
	 * See {@link CommentInterface::$use_ajax_commenting}
	 *
	 * @param bool
	 */
	static function set_use_ajax_commenting($state) {
		self::$use_ajax_commenting = $state;
	}
	
	/**
	 * @return boolean true if the currently logged in user can post a comment,
	 * false if they can't. Users can post comments by default, enforce 
	 * security by using 
	 *
	 * @link CommentInterface::set_comments_require_login() and 
	 * @link {CommentInterface::set_comments_require_permission()}.
	 */
	public static function canPost() {
		$member = Member::currentUser();
		
		if(self::$comments_require_permission && $member && Permission::check(self::$comments_require_permission)) {
			// Comments require a certain permission, and the user has the correct permission
			return true; 
			
		} elseif(self::$comments_require_login && $member && !self::$comments_require_permission) {
			// Comments only require that a member is logged in
			return true;
			
		} elseif(!self::$comments_require_permission && !self::$comments_require_login) {
			// Comments don't require anything - anyone can add a comment
			return true; 
		}
		
		return false;
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
	
	function Comments() {
		// Comment limits
		$limit = array();
		$limit['start'] = isset($_GET['commentStart']) ? (int)$_GET['commentStart'] : 0;
		$limit['limit'] = Comment::$comments_per_page;
		
		$spamfilter = isset($_GET['showspam']) ? '' : "AND \"IsSpam\" = 0";
		$unmoderatedfilter = Permission::check('CMS_ACCESS_CommentAdmin') ? '' : "AND \"NeedsModeration\" = 0";
		$order = self::$order_comments_by;
		$comments =  DataObject::get("Comment", "\"ParentID\" = '" . Convert::raw2sql($this->page->ID) . "' $spamfilter $unmoderatedfilter", $order, "", $limit);
		
		if(is_null($comments)) {
			return;
		}
		
		// This allows us to use the normal 'start' GET variables as well (In the weird circumstance where you have paginated comments AND something else paginated)
		$comments->setPaginationGetVar('commentStart');
		
		return $comments;
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

/**
 * @package comments
 */
class CommentInterface_Form extends Form {
	
}

/**
 * @package comments
 */
class CommentInterface_Controller extends ContentController {
	function __construct() {
		parent::__construct(null);
	}
	
	function newspamquestion() {
		if(Director::is_ajax()) {
			echo Convert::raw2xml(sprintf(_t('CommentInterface_Controller.SPAMQUESTION', "Spam protection question: %s"),MathSpamProtection::getMathQuestion()));
		}
	}
}