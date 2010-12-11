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
		"BaseClass"		=> "Varchar(200)",
		"Moderated"		=> "Boolean",
		"IsSpam"		=> "Boolean"
	);

	static $has_one = array(
		"Parent"		=> "DataObject",
		"Author"		=> "Member"
	);
	
	static $has_many = array();
	
	static $many_many = array();
	
	static $defaults = array(
		"Moderated" => true
	);
	
	static $casting = array(
		"RSSTitle" => "Varchar",
	);
	
	/**
	 * Migrates the old {@link PageComment} objects to {@link Comment}
	 */
	public function requireDefaultRecords() {
		parent::requireDefaultRecords();
		
		if(DB::getConn()->hasTable('PageComment')) {
			$comments = DB::query("SELECT * FROM \"PageComment\"");
			
			if($comments) {
				while($pageComment = $comments->numRecord()) {
					// create a new comment from the older page comment
					$comment = new Comment($pageComment);
					
					// set the variables which have changed
					$comment->BaseClass = 'SiteTree';
					$comment->URL = (isset($pageComment['CommenterURL'])) ? $pageComment['CommenterURL'] : "";
					
					$comment->write();
				}
			}
			
			DB::alterationMessage("Migrated PageComment to Comment","changed");
			DB::getConn()->dontRequireTable('PageComment');
		}
	}
	 
	/**
	 * Return a link to this comment
	 *
	 * @return string link to this comment.
	 */
	public function Link($action = "") {
		return $this->Parent()->Link($action) . '#' . $this->Permalink();
	}
	
	/**
	 * Returns the permalink for this {@link Comment}. Inserted into
	 * the ID tag of the comment
	 *
	 * @return string
	 */
	public function Permalink() {
		$prefix = Commenting::get_config_value($this->BaseClass, 'comment_permalink_prefix');
		
		return $prefix . $this->ID;
	}
	
	/**
	 * @param boolean $includerelations a boolean value to indicate if the labels returned include relation fields
	 */
	function fieldLabels($includerelations = true) {
		$labels = parent::fieldLabels($includerelations);
		$labels['Name'] = _t('Comment.NAME', 'Author Name');
		$labels['Comment'] = _t('Comment.COMMENT', 'Comment');
		$labels['IsSpam'] = _t('Comment.ISSPAM', 'Spam?');
		$labels['NeedsModeration'] = _t('Comment.NEEDSMODERATION', 'Needs Moderation?');
		
		return $labels;
	}
	
	/**
	 * Returns the parent {@link DataObject} this comment is attached too
	 *
	 * @return DataObject
	 */
	public function getParent() {
		if(!$this->BaseClass) $this->BaseClass = "SiteTree";
		
		return DataObject::get_by_id($this->BaseClass, $this->ParentID);
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
	 * @todo needs to compare to the new {@link Commenting} configuration API
	 *
	 * @return Boolean
	 */
	function canCreate($member = null) {
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
		
		$page = $this->getParent();
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
	
	
	/************************************ Review the following */
	function getRSSName() {
		if($this->Name) {
			return $this->Name;
		} elseif($this->Author()) {
			return $this->Author()->getName();
		}
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
}