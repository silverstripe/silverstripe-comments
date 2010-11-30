<?php

/**
 * Represents a single comment object.
 * 
 * @package comments
 */
class Comment extends DataObject {
	
	static $db = array(
		"Name"			=> "Varchar(200)",
		"Comment"		=> "Text",
		"Email"			=> "Varchar(200)",
		"URL"			=> "Varchar(255)",
		"SessionID"		=> "Varchar(255)",
		"ParentClass"	=> "Varchar(200)"
	);

	static $has_one = array(
		"Parent"		=> "DataObject",
		"Author"		=> "Member"
	);
	
	static $has_many = array();
	
	static $many_many = array();
	
	static $defaults = array();
	
	static $casting = array(
		"RSSTitle" => "Varchar",
	);

	static $comments_per_page = 10;
	
	static $moderate = false;
	
	static $bbcode = false;

	/**
	 * Return a link to this comment
	 *
	 * @return string link to this comment.
	 */
	function Link() {
		return $this->Parent()->Link() . '#PageComment_'. $this->ID;
	}
	
	function getRSSName() {
		if($this->Name) {
			return $this->Name;
		} elseif($this->Author()) {
			return $this->Author()->getName();
		}
	}
	
	function ParsedBBCode(){
		$parser = new BBCodeParser($this->Comment);
		return $parser->parse();		
	}

	function DeleteLink() {
		return ($this->canDelete()) ? "PageComment_Controller/deletecomment/$this->ID" : false;
	}
	
	function CommentTextWithLinks() {
		$pattern = '|([a-zA-Z]+://)([a-zA-Z0-9?&%.;:/=+_-]*)|is';
		$replace = '<a rel="nofollow" href="$1$2">$1$2</a>';
		return preg_replace($pattern, $replace, $this->Comment);
	}
	
	function SpamLink() {
		return ($this->canEdit() && !$this->IsSpam) ? "PageComment_Controller/reportspam/$this->ID" : false;
	}
	
	function HamLink() {
		return ($this->canEdit() && $this->IsSpam) ? "PageComment_Controller/reportham/$this->ID" : false;
	}
	
	function ApproveLink() {
		return ($this->canEdit() && $this->NeedsModeration) ? "PageComment_Controller/approve/$this->ID" : false;
	}
	
	function SpamClass() {
		if($this->getField('IsSpam')) {
			return 'spam';
		} else if($this->getField('NeedsModeration')) {
			return 'unmoderated';
		} else {
			return 'notspam';
		}
	}
	
	
	function RSSTitle() {
		return sprintf(
			_t('PageComment.COMMENTBY', "Comment by '%s' on %s", PR_MEDIUM, 'Name, Page Title'),
			Convert::raw2xml($this->getRSSName()),
			$this->Parent()->Title
		);
	}
	


	
	function PageTitle() {
		return $this->Parent()->Title;
	}
	
	static function enableModeration() {
		self::$moderate = true;
	}	

	static function moderationEnabled() {
		return self::$moderate;
	}
	
	static function enableBBCode() {
		self::$bbcode = true;
	}	

	static function bbCodeEnabled() {
		return self::$bbcode;
	}
	
	/**
	 *
	 * @param boolean $includerelations a boolean value to indicate if the labels returned include relation fields
	 * 
	 */
	function fieldLabels($includerelations = true) {
		$labels = parent::fieldLabels($includerelations);
		$labels['Name'] = _t('PageComment.Name', 'Author Name');
		$labels['Comment'] = _t('PageComment.Comment', 'Comment');
		$labels['IsSpam'] = _t('PageComment.IsSpam', 'Spam?');
		$labels['NeedsModeration'] = _t('PageComment.NeedsModeration', 'Needs Moderation?');
		
		return $labels;
	}
	
	/**
	 * This method is called just before this object is
	 * written to the database.
	 * 
	 * Specifically, make sure "http://" exists at the start
	 * of the URL, if it doesn't have https:// or http://
	 */
	public function onBeforeWrite() {
		parent::onBeforeWrite();
		
		$url = $this->CommenterURL;
		
		if($url) {
			if(strtolower(substr($url, 0, 8)) != 'https://' && strtolower(substr($url, 0, 7)) != 'http://') { 
				$this->CommenterURL = 'http://' . $url; 
			}
		}
	}
	
	/**
	 * This always returns true, and should be handled by {@link PageCommentInterface->CanPostComment()}.
	 * 
	 * @todo Integrate with PageCommentInterface::$comments_require_permission and $comments_require_login
	 * 
	 * @param Member $member
	 * @return Boolean
	 */
	function canCreate($member = null) {
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
	 * Checks for association with a page,
	 * and {@link SiteTree->ProvidePermission} flag being set to TRUE.
	 * Note: There's an additional layer of permission control
	 * in {@link PageCommentInterface}.
	 * 
	 * @param Member $member
	 * @return Boolean
	 */
	function canView($member = null) {
		if(!$member) $member = Member::currentUser();
		
		// Standard mechanism for accepting permission changes from decorators
		$extended = $this->extendedCan('canView', $member);
		if($extended !== null) return $extended;
		
		$page = $this->Parent();
		return (
			($page && $page->ProvideComments)
			|| (bool)Permission::checkMember($member, 'CMS_ACCESS_CommentAdmin')
		);
	}
	
	/**
	 * Checks for "CMS_ACCESS_CommentAdmin" permission codes
	 * and {@link canView()}. 
	 * 
	 * @param Member $member
	 * @return Boolean
	 */
	function canEdit($member = null) {
		if(!$member) $member = Member::currentUser();
		
		// Standard mechanism for accepting permission changes from decorators
		$extended = $this->extendedCan('canEdit', $member);
		if($extended !== null) return $extended;
		
		if(!$this->canView($member)) return false;
		
		return (bool)Permission::checkMember($member, 'CMS_ACCESS_CommentAdmin');
	}
	
	/**
	 * Checks for "CMS_ACCESS_CommentAdmin" permission codes
	 * and {@link canEdit()}.
	 * 
	 * @param Member $member
	 * @return Boolean
	 */
	function canDelete($member = null) {
		if(!$member) $member = Member::currentUser();
		
		// Standard mechanism for accepting permission changes from decorators
		$extended = $this->extendedCan('canDelete', $member);
		if($extended !== null) return $extended;
		
		return $this->canEdit($member);
	}
}