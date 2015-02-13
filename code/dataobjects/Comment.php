<?php

/**
 * Represents a single comment object.
 * 
 * @package comments
 */
class Comment extends DataObject {
	
	private static $db = array(
		"Name"			=> "Varchar(200)",
		"Comment"		=> "Text",
		"Email"			=> "Varchar(200)",
		"URL"			=> "Varchar(255)",
		"BaseClass"		=> "Varchar(200)",
		"Moderated"		=> "Boolean",
		"IsSpam"		=> "Boolean",
		"ParentID"		=> "Int",
		'AllowHtml'		=> "Boolean",
		"ParentID"		=> "Int",
		"Depth" => 'Int',
		'Lineage' => 'Varchar(255)',
		'MarkedAsDeleted' => 'Boolean'
	);

	private static $has_one = array(
		"Author"		=> "Member",
		"ParentComment" => 'Comment'
	);
	
	private static $default_sort = '"Created" DESC';
	
	private static $has_many = array();
	
	private static $many_many = array();
	
	private static $defaults = array(
		"Moderated" => 1,
		"IsSpam" => 0
	);

	private static $indexes = array(
		'Depth' => true,
		'Lineage' => true
	);
	
	private static $casting = array(
		'AuthorName' => 'Varchar',
		'RSSName' => 'Varchar'
	);

	private static $searchable_fields = array(
		'Name',
		'Email',
		'Comment',
		'Created',
		'BaseClass',
	);
	
	private static $summary_fields = array(
		'Name' => 'Submitted By',
		'Email' => 'Email',
		'Comment' => 'Comment',
		'Created' => 'Date Posted',
		'ParentTitle' => 'Parent',
		'IsSpam' => 'Is Spam'
	);

	public function onAfterWrite() {
		parent::onAfterWrite();

		if (!$this->LineageFixed) {
			// Sanitize HTML, because its expected to be passed to the template unescaped later
			if($this->AllowHtml) {
				$this->Comment = $this->purifyHtml($this->Comment);
			}

			// Calculate depth and lineage from parent comment
			if ($this->ParentCommentID == 0) {
				$this->Depth = 1;
				$this->Lineage = $this->paddedNumber($this->ID);
			} else {
				$pc = $this->ParentComment();
				$this->Depth = $pc->Depth + 1;
				$this->Lineage = ($pc->Lineage).$this->paddedNumber($this->ID);
			}

			$this->LineageFixed = true;

			$this->write();
		}

		
	}


	private function paddedNumber($i) {
		// fixme, use config
		return str_pad($i, 5, '0', STR_PAD_LEFT);
	}
	
	/**
	 * Migrates the old {@link PageComment} objects to {@link Comment}
	 */
	public function requireDefaultRecords() {
		parent::requireDefaultRecords();
		
		if(DB::getConn()->hasTable('PageComment')) {
			$comments = DB::query("SELECT * FROM \"PageComment\"");
			
			if($comments) {
				while($pageComment = $comments->nextRecord()) {
					// create a new comment from the older page comment
					$comment = new Comment();
					$comment->update($pageComment);
					
					// set the variables which have changed
					$comment->BaseClass = 'SiteTree';
					$comment->URL = (isset($pageComment['CommenterURL'])) ? $pageComment['CommenterURL'] : "";
					if((int)$pageComment['NeedsModeration'] == 0) $comment->Moderated = true;
					
					$comment->write();
				}
			}
			
			DB::alteration_message("Migrated PageComment to Comment","changed");
			DB::getConn()->dontRequireTable('PageComment');
		}

		DB::query('UPDATE Comment set Depth=1 where ParentCommentID = 0;');

		// add depth to comments missing this value
		$maxthreaddepth = Commenting::get_config_value($this->Class, 'maximum_thread_comment_depth');

		for ($i=1; $i < $maxthreaddepth; $i++) { 
			$sql = "UPDATE Comment c1\n".
			"INNER JOIN Comment c2\n".
			"ON c1.ID = c2.ParentCommentID\n".
			"SET c2.Depth=".($i+1)." WHERE c1.Depth=".$i.";";
			DB::query($sql);
		}

		DB::alteration_message("Updated missing depth values from comment hierarchy","changed");

		// now fix any missing lineage
		for ($i=1; $i < $maxthreaddepth; $i++) {
			$comments = Comment::get()->filter('Depth',$i)->where('Lineage is NULL');
			foreach ($comments as $comment) {
				$comment->write();
				DB::alteration_message("Lineage fixed for comment ".$comment->write(),"changed");
			}

		}
	}
	 
	/**
	 * Return a link to this comment
	 *
	 * @return string link to this comment.
	 */
	public function Link($action = "") {
	  if($parent = $this->getParent()){
		return $parent->Link($action) . '#' . $this->Permalink();
	  }
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
	 * Translate the form field labels for the CMS administration
	 *
	 * @param boolean $includerelations
	 */
	public function fieldLabels($includerelations = true) {
		$labels = parent::fieldLabels($includerelations);
		$labels['Name'] = _t('Comment.NAME', 'Author Name');
		$labels['Comment'] = _t('Comment.COMMENT', 'Comment');
		$labels['Email'] = _t('Comment.EMAIL', 'Email');
		$labels['URL'] = _t('Comment.URL', 'URL');
		$labels['IsSpam'] = _t('Comment.ISSPAM', 'Spam?');
		$labels['Moderated'] = _t('Comment.MODERATED', 'Moderated?');
		$labels['ParentTitle'] = _t('Comment.PARENTTITLE', 'Parent');
		$labels['Created'] = _t('Comment.CREATED', 'Date posted');
		
		return $labels;
	}
	
	/**
	 * Returns the parent {@link DataObject} this comment is attached too
	 *
	 * @return DataObject
	 */
	public function getParent() {
		if(!$this->BaseClass) {
			$this->BaseClass = "SiteTree";
		}
		
		return ($this->ParentID) ? DataObject::get_by_id($this->BaseClass, $this->ParentID) : null;
	}


	/**
	 * Returns a string to help identify the parent of the comment
	 *
	 * @return string
	 */
	public function getParentTitle() {
	  if($parent = $this->getParent()){
		return ($parent && $parent->Title) ? $parent->Title : $parent->ClassName . " #" . $parent->ID;
	  }
	}

	/**
	 * Comment-parent classnames obviousely vary, return the parent classname
	 *
	 * @return string
	 */
	public function getParentClassName() {
		$default = 'SiteTree';
		
		if(!$this->BaseClass) {
			return $default;
		}

		return $this->BaseClass;
	}
	
	/**
	 * Return the content for this comment escaped depending on the Html state.
	 *
	 * @return HTMLText
	 */
	public function getEscapedComment() {
		$comment = $this->dbObject('Comment');

		if ($comment->exists()) {
			if ($this->AllowHtml) {
				return DBField::create_field('HTMLText', nl2br($comment->RAW()));
			} else {
				return DBField::create_field('HTMLText', sprintf("<p>%s</p>", nl2br($comment->XML())));
			}
		}

		return $comment;
	}

	/**
	 * Return whether this comment is a preview (has not been written to the db)
	 *
	 * @return boolean
	 */
	public function isPreview() {
		return ($this->ID < 1);
	}

	/**
	 * @todo needs to compare to the new {@link Commenting} configuration API
	 *
	 * @return Boolean
	 */
	public function canCreate($member = null) {
		return false;
	}

	/**
	 * Checks for association with a page, and {@link SiteTree->ProvidePermission} 
	 * flag being set to true.
	 * 
	 * @param Member $member
	 * @return Boolean
	 */
	public function canView($member = null) {
		if(!$member) $member = Member::currentUser();
		
		// Standard mechanism for accepting permission changes from decorators
		$extended = $this->extendedCan('canView', $member);
		if($extended !== null) return $extended;
		
		$page = $this->getParent();
		$admin = (bool) Permission::checkMember($member, 'CMS_ACCESS_CommentAdmin');

		return (($page && $page->ProvideComments && $page->canView($member)) || $admin);
	}
	
	/**
	 * Checks for "CMS_ACCESS_CommentAdmin" permission codes and 
	 * {@link canView()}. 
	 * 
	 * @param Member $member
	 * @return Boolean
	 */
	public function canEdit($member = null) {
		if(!$member) $member = Member::currentUser();
		
		// Standard mechanism for accepting permission changes from decorators
		$extended = $this->extendedCan('canEdit', $member);
		if($extended !== null) return $extended;
		
		if(!$this->canView($member)) return false;
		
		return (bool)Permission::checkMember($member, 'CMS_ACCESS_CommentAdmin');
	}
	
	/**
	 * Checks for "CMS_ACCESS_CommentAdmin" permission codes and 
	 * {@link canEdit()}.
	 * 
	 * @param Member $member
	 * @return Boolean
	 */
	public function canDelete($member = null) {
		if(!$member) $member = Member::currentUser();
		
		// Standard mechanism for accepting permission changes from decorators
		$extended = $this->extendedCan('canDelete', $member);
		if($extended !== null) return $extended;
		
		return $this->canEdit($member);
	}

	/**
	 * Return the authors name for the comment
	 *
	 * @return string
	 */
	public function getAuthorName() {
		if($this->Name) {
			return $this->Name;
		} else if($this->Author()) {
			return $this->Author()->getName();
		}
	}

	/**
	 * @return string
	 */
	public function DeleteLink() {
		if($this->canDelete()) {
			$token = SecurityToken::inst();

			return DBField::create_field("Varchar", Director::absoluteURL($token->addToUrl(sprintf(
				"CommentingController/delete/%s", (int) $this->ID
			))));
		}
	}
	
	/**
	 * @return string
	 */
	public function SpamLink() {
		if($this->canEdit() && !$this->IsSpam) {
			$token = SecurityToken::inst();

			return DBField::create_field("Varchar", Director::absoluteURL($token->addToUrl(sprintf(
				"CommentingController/spam/%s", (int) $this->ID
			))));
		}
	}
	
	/**
	 * @return string
	 */
	public function HamLink() {
		if($this->canEdit() && $this->IsSpam) {
			$token = SecurityToken::inst();

			return DBField::create_field("Varchar", Director::absoluteURL($token->addToUrl(sprintf(
				"CommentingController/ham/%s", (int) $this->ID
			))));
		}
	}
	
	/**
	 * @return string
	 */
	public function ApproveLink() {
		if($this->canEdit() && !$this->Moderated) {
			$token = SecurityToken::inst();

			return DBField::create_field("Varchar", Director::absoluteURL($token->addToUrl(sprintf(
				"CommentingController/approve/%s", (int) $this->ID
			))));
		}
	}

	/**
	 * @return string
	 */
	public function ViewCommentLink() {
		if($this->canView() && $this->Moderated) {
			$token = SecurityToken::inst();

			return DBField::create_field("Varchar", Director::absoluteURL($token->addToUrl(sprintf(
				"CommentingController/viewcomment/%s", 'COMMENTID'
			))));
		} else {
			return 'lolnope';
		}
	}
	
	/**
	 * @return string
	 */
	public function SpamClass() {
		if($this->IsSpam) {
			return 'spam';
		} else if(!$this->Moderated) {
			return 'unmoderated';
		} else {
			return 'notspam';
		}
	}
	
	/**
	 * @return string
	 */
	public function getTitle() {
		$title = sprintf(_t('Comment.COMMENTBY', "Comment by %s", 'Name'), $this->getAuthorName());

		if($parent = $this->getParent()) {
			if($parent->Title) {
				$title .= sprintf(" %s %s", _t('Comment.ON', 'on'), $parent->Title);
			}
		}

		return $title;
	}

	/*
	 * Modify the default fields shown to the user
	 */
	public function getCMSFields() {
		$fields = parent::getCMSFields();
		$parent = $this->getParent()->ID;

		$hidden = array('ParentID', 'AuthorID', 'BaseClass', 'AllowHtml');

		foreach($hidden as $private) {
			$fields->removeByName($private);
		}

		return $fields;
	}

	/**
	 * @param  String $dirtyHtml
	 * @return String
	 */
	public function purifyHtml($dirtyHtml) {
		$purifier = $this->getHtmlPurifierService();
		return $purifier->purify($dirtyHtml);
	}

	/**
	 * @return HTMLPurifier (or anything with a "purify()" method)
	 */
	public function getHtmlPurifierService() {
		$config = HTMLPurifier_Config::createDefault();
		$config->set('HTML.AllowedElements',
			Commenting::get_config_value($this->BaseClass, 'html_allowed_elements')
		);
		$config->set('AutoFormat.AutoParagraph', true);
		$config->set('AutoFormat.Linkify', true);
		$config->set('URI.DisableExternalResources', true);
		$config->set('Cache.SerializerPath', getTempFolder());
		return new HTMLPurifier($config);
	}

	/*
	Calcualate the gravatar link from the email address
	*/
	public function Gravatar() {
		$gravatar = '';
		$use_gravatar = Commenting::get_config_value($this->BaseClass, 'use_gravatar');
		if ($use_gravatar) {
			$gravatar = "http://www.gravatar.com/avatar/" . md5( strtolower(trim($this->Email)));
			$gravatarsize = Commenting::get_config_value($this->BaseClass, 'gravatar_size');
			$gravatardefault = Commenting::get_config_value($this->BaseClass, 'gravatar_default');
			$gravatarrating = Commenting::get_config_value($this->BaseClass, 'gravatar_rating');
			$gravatar.= "?s=".$gravatarsize."&d=".$gravatardefault."&r=".$gravatarrating;
		}

		return $gravatar;
	}

	/*
	Check if this comment can be replied to
	- check threading is enabled
	- comment must have been moderated
	*/
	public function CanReply() {
		$threaded = Commenting::get_config_value($this->BaseClass, 'thread_comments');
		$maxdepth = Commenting::get_config_value($this->BaseClass, 'maximum_thread_comment_depth');
		$disabled = $this->Disabled;
		$moderated = $this->Moderated;
		return  $threaded && $this->Moderated && $this->Depth < $maxdepth && !$disabled && $this->Moderated;
	}
}
