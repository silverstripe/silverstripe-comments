<?php

/**
 * Extension to {@link DataObject} to enable tracking comments.
 *
 * @package comments
 */

class CommentsExtension extends DataExtension {

	/**
	 * Default configuration values
	 * 
	 * @var array 
	 * @config
	 */
	private static $comments = array(
		'enabled' => true, // Allows commenting to be disabled even if the extension is present
		'enabled_cms' => false, // Allows commenting to be enabled or disabled via the CMS
		'require_login' => false, // boolean, whether a user needs to login
		'require_login_cms' => false, // Allows require_login to be set via the CMS
		// required permission to comment (or array of permissions). require_login must be set for this to work
		'required_permission' => false,
		'include_js' => true, // Enhance operation by ajax behaviour on moderation links
		'use_gravatar' => false, // set to true to show gravatar icons,
		'gravatar_size' => 80, // size of gravatar in pixels.  This is the same as the standard default
		// theme for 'not found' gravatar (see http://gravatar.com/site/implement/images/)
		'gravatar_default' => 'identicon',
		'gravatar_rating' => 'g', // gravatar rating. This is the same as the standard default
		// when comments are disabled should we show older comments (if available)
		'show_comments_when_disabled' => false,
		'order_comments_by' => "\"Created\" DESC",
		'comments_per_page' => 10,
		'comments_holder_id' => "comments-holder", // id for the comments holder
		'comment_permalink_prefix' => "comment-", // id prefix for each comment. If needed make this different
		'require_moderation' => false, // Require moderation for all comments
		// requires moderation for comments posted by non-members. 'require_moderation' overrides this if set.
		'require_moderation_nonmembers' => false,
		// If true, ignore above values and configure moderation requirements via the CMS only
		'require_moderation_cms' => false,
		'html_allowed' => false, // allow for sanitized HTML in comments
		'html_allowed_elements' => array('a', 'img', 'i', 'b'),
		'use_preview' => false, // preview formatted comment (when allowing HTML). Requires include_js=true
	);

	private static $db = array(
		'ProvideComments' => 'Boolean',
		'ModerationRequired' => "Enum('None,Required,NonMembersOnly','None')",
		'CommentsRequireLogin' => 'Boolean',
	);

	/**
	 * CMS configurable options should default to the config values
	 */
	public function populateDefaults() {
		// Set if comments should be enabled by default
		$this->owner->ProvideComments = $this->owner->getCommentsOption('enabled') ? 1 : 0;

		// If moderations options should be configurable via the CMS then
		if($this->owner->getCommentsOption('require_moderation')) {
			$this->owner->ModerationRequired = 'Required';
		} elseif($this->owner->getCommentsOption('require_moderation_nonmembers')) {
			$this->owner->ModerationRequired = 'NonMembersOnly';
		} else {
			$this->owner->ModerationRequired = 'None';
		}

		$this->owner->CommentsRequireLogin = $this->owner->getCommentsOption('require_login') ? 1 : 0;
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

		$options = FieldGroup::create()->setTitle(_t('CommentsExtension.COMMENTOPTIONS', 'Comments'));
		
		// Check if enabled setting should be cms configurable
		if($this->owner->getCommentsOption('enabled_cms')) {
			$options->push(new CheckboxField('ProvideComments', _t('Comment.ALLOWCOMMENTS', 'Allow Comments')));
		}

		// Check if we should require users to login to comment
		if($this->owner->getCommentsOption('require_login_cms')) {
			$options->push(
				new CheckboxField(
					'CommentsRequireLogin',
					_t('Comments.COMMENTSREQUIRELOGIN', 'Require login to comment')
				)
			);
		}

		if($options->FieldList()->count()) {
			if($fields->hasTabSet()) {
				$fields->addFieldsToTab('Root.Settings', $options);
			} else {
				$fields->push($options);
			}
		}

		// Check if moderation should be enabled via cms configurable
		if($this->owner->getCommentsOption('require_moderation_cms')) {
			$moderationField = new DropdownField('ModerationRequired', 'Comment Moderation', array(
				'None' => _t('CommentsExtension.MODERATIONREQUIRED_NONE', 'No moderation required'),
				'Required' => _t('CommentsExtension.MODERATIONREQUIRED_REQUIRED', 'Moderate all comments'),
				'NonMembersOnly' => _t(
					'CommentsExtension.MODERATIONREQUIRED_NONMEMBERSONLY',
					'Only moderate non-members'
				),
			));
			if($fields->hasTabSet()) {
				$fields->addFieldsToTab('Root.Settings', $moderationField);
			} else {
				$fields->push($moderationField);
			}
		}
	}

	/**
	 * Returns the RelationList of all comments against this object. Can be used as a data source
	 * for a gridfield with write access.
	 *
	 * @return CommentList
	 */
	public function AllComments() {
		$comments = CommentList::create($this->ownerBaseClass)->forForeignID($this->owner->ID);
		$this->owner->extend('updateAllComments', $comments);
		return $comments;
	}

	public function getComments() {
		Deprecation::notice('2.0',  'Use PagedComments to get paged coments');
		return $this->PagedComments();
	}

	/**
	 * Get comment moderation rules for this parent
	 *
	 * @return string A value of either 'None' (no moderation required), 'Required' (all comments),
	 * or 'NonMembersOnly' (only not logged in users)
	 */
	public function getModerationRequired() {
		if($this->owner->getCommentsOption('require_moderation_cms')) {
			return $this->owner->getField('ModerationRequired');
		} elseif($this->owner->getCommentsOption('require_moderation')) {
			return 'Required';
		} elseif($this->owner->getCommentsOption('require_moderation_nonmembers')) {
			return 'NonMembersOnly';
		} else {
			return 'None';
		}
	}

	/**
	 * Determine if users must be logged in to post comments
	 *
	 * @return boolean
	 */
	public function getCommentsRequireLogin() {
		if($this->owner->getCommentsOption('require_login_cms')) {
			return (bool)$this->owner->getField('CommentsRequireLogin');
		} else {
			return (bool)$this->owner->getCommentsOption('require_login');
		}
	}

	/**
	 * Returns the root level comments, with spam and unmoderated items excluded, for use in the frontend
	 *
	 * @return CommentList
	 */
	public function Comments() {
		// Get all non-spam comments
		$order = $this->owner->getCommentsOption('order_comments_by');
		$list = $this
			->AllComments()
			->sort($order)
			->filter('IsSpam', 0);
		
		// Filter unmoderated comments for non-administrators if moderation is enabled
		if ($this->owner->ModerationRequired !== 'None') {
		    $list = $list->filter('Moderated', 1);
		}

		$this->owner->extend('updateComments', $list);
		return $list;
	}

	/**
	 * Returns a paged list of the root level comments, with spam and unmoderated items excluded,
	 * for use in the frontend
	 *
	 * @return PaginatedList
	 */
	public function PagedComments() {
		$list = $this->Comments();
		
		// Add pagination
		$list = new PaginatedList($list, Controller::curr()->getRequest());
		$list->setPaginationGetVar('commentsstart'.$this->owner->ID);
		$list->setPageLength($this->owner->getCommentsOption('comments_per_page'));

		$this->owner->extend('updatePagedComments', $list);
		return $list;
	}

	/**
	 * Check if comments are configured for this page even if they are currently disabled.
	 * Do not include the comments on pages which don't have id's such as security pages
	 *
	 * @deprecated since version 2.0
	 *
	 * @return boolean
	 */
	public function getCommentsConfigured() {
		Deprecation::notice('2.0', 'getCommentsConfigured is deprecated. Use getCommentsEnabled instead');
		return true; // by virtue of all classes with this extension being 'configured'
	}

	/**
	 * Determine if comments are enabled for this instance
	 *
	 * @return boolean
	 */
	public function getCommentsEnabled() {
		// Determine which flag should be used to determine if this is enabled
		if($this->owner->getCommentsOption('enabled_cms')) {
			return $this->owner->ProvideComments;
		} else {
			return $this->owner->getCommentsOption('enabled');
		}
	}

	/**
	 * Get the HTML ID for the comment holder in the template
	 *
	 * @return string
	 */
	public function getCommentHolderID() {
		return $this->owner->getCommentsOption('comments_holder_id');
	}

	/**
	 * @deprecated since version 2.0
	 */
	public function getPostingRequiresPermission() {
		Deprecation::notice('2.0', 'Use getPostingRequiredPermission instead');
		return $this->getPostingRequiredPermission();
	}

	/**
	 * Permission codes required in order to post (or empty if none required)
	 *
	 * @return string|array Permission or list of permissions, if required
	 */
	public function getPostingRequiredPermission() {
		return $this->owner->getCommentsOption('required_permission');
	}

	public function canPost() {
		Deprecation::notice('2.0', 'Use canPostComment instead');
		return $this->canPostComment();
	}

	/**
	 * Determine if a user can post comments on this item
	 *
	 * @param Member $member Member to check
	 * @return boolean
	 */
	public function canPostComment($member = null) {
		// Deny if not enabled for this object
		if(!$this->owner->CommentsEnabled) return false;

		// Check if member is required
		$requireLogin = $this->owner->CommentsRequireLogin;
		if(!$requireLogin) return true;

		// Check member is logged in
		$member = $member ?: Member::currentUser();
		if(!$member) return false;

		// If member required check permissions
		$requiredPermission = $this->owner->PostingRequiredPermission;
		if($requiredPermission && !Permission::checkMember($member, $requiredPermission)) return false;

		return true;
	}

	/**
	 * Determine if this member can moderate comments in the CMS
	 *
	 * @param Member $member
	 * @return boolean
	 */
	public function canModerateComments($member = null) {
		return $this->owner->canEdit($member);
	}

	public function getRssLink() {
		Deprecation::notice('2.0', 'Use getCommentRSSLink instead');
		return $this->getCommentRSSLink();
	}

	/**
	 * Gets the RSS link to all comments
	 *
	 * @return string
	 */
	public function getCommentRSSLink() {
		return Controller::join_links(Director::baseURL(), "CommentingController/rss");
	}

	public function getRssLinkPage() {
		Deprecation::notice('2.0', 'Use getCommentRSSLinkPage instead');
		return $this->getCommentRSSLinkPage();
	}

	/**
	 * Get the RSS link to all comments on this page
	 *
	 * @return string
	 */
	public function getCommentRSSLinkPage() {
		return Controller::join_links(
			$this->getCommentRSSLink(), $this->ownerBaseClass, $this->owner->ID
		);
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
		// Check if enabled
		$enabled = $this->getCommentsEnabled();
		if($enabled && $this->owner->getCommentsOption('include_js')) {
			Requirements::javascript(THIRDPARTY_DIR . '/jquery/jquery.js');
			Requirements::javascript(THIRDPARTY_DIR . '/jquery-validate/lib/jquery.form.js');
			Requirements::javascript(THIRDPARTY_DIR . '/jquery-validate/jquery.validate.pack.js');
			Requirements::javascript('comments/javascript/CommentsInterface.js');
		}
		
		$controller = CommentingController::create();
		$controller->setOwnerRecord($this->owner);
		$controller->setBaseClass($this->ownerBaseClass);
		$controller->setOwnerController(Controller::curr());

		$moderatedSubmitted = Session::get('CommentsModerated');
		Session::clear('CommentsModerated');

		$form = ($enabled) ? $controller->CommentsForm() : false;

		// a little bit all over the show but to ensure a slightly easier upgrade for users
		// return back the same variables as previously done in comments
		return $this
			->owner
			->customise(array(
				'AddCommentForm' => $form,
				'ModeratedSubmitted' => $moderatedSubmitted,
			))
			->renderWith('CommentsInterface');
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
		Deprecation::notice('1.0', '$PageComments is deprecated. Please use $CommentsForm');
		return $this->CommentsForm();
	}

	/**
	 * Get the commenting option for this object
	 *
	 * This can be overridden in any instance or extension to customise the option available
	 *
	 * @param string $key
	 * @return mixed Result if the setting is available, or null otherwise
	 */
	public function getCommentsOption($key) {
		$settings = $this->owner // In case singleton is called on the extension directly
			? $this->owner->config()->comments
			: Config::inst()->get(__CLASS__, 'comments');
		$value = null;
		if(isset($settings[$key])) $value = $settings[$key];

		// To allow other extensions to customise this option
		if($this->owner) $this->owner->extend('updateCommentsOption', $key, $value);
		return $value;
	}

	/**
	 * Add moderation functions to the current fieldlist
	 *
	 * @param FieldList $fields
	 */
	protected function updateModerationFields(FieldList $fields) {
		// Create gridfield config
		$commentsConfig = CommentsGridFieldConfig::create();

		$needs = new GridField(
			'CommentsNeedsModeration',
			_t('CommentsAdmin.NeedsModeration', 'Needs Moderation'),
			$this->owner->AllComments()->filter('Moderated', 0),
			$commentsConfig
		);

		$moderated = new GridField(
			'CommentsModerated',
			_t('CommentsAdmin.Moderated', 'Moderated'),
			$this->owner->AllComments()->filter('Moderated', 1),
			$commentsConfig
		);

		if($fields->hasTabSet()) {
			$tabset = new TabSet(
				'Comments',
				new Tab('CommentsNeedsModerationTab', _t('CommentAdmin.NeedsModeration', 'Needs Moderation'),
					$needs
				),
				new Tab('CommentsModeratedTab', _t('CommentAdmin.Moderated', 'Moderated'),
					$moderated
				)
			);
			 $fields->addFieldToTab('Root', $tabset);
		} else {
			$fields->push($needs);
			$fields->push($moderated);
		}
	}

	public function updateCMSFields(\FieldList $fields) {
		// Disable moderation if not permitted
		if($this->owner->canModerateComments()) {
			$this->updateModerationFields($fields);
		}

		// If this isn't a page we should merge the settings into the CMS fields
		if(!$this->attachedToSiteTree()) {
			$this->updateSettingsFields($fields);
		}
	}
}
