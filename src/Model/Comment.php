<?php

namespace SilverStripe\Comments\Model;

use HTMLPurifier;
use HTMLPurifier_Config;
use SilverStripe\Comments\Controllers\CommentingController;
use SilverStripe\Comments\Extensions\CommentsExtension;
use SilverStripe\Comments\Model\Comment\SecurityToken;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\TempFolder;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\EmailField;
use SilverStripe\Forms\FieldGroup;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\HTMLEditor\HTMLEditorField;
use SilverStripe\Forms\DatetimeField;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Forms\TextField;
use SilverStripe\Model\List\PaginatedList;
use SilverStripe\Model\List\ArrayList;
use SilverStripe\Model\List\SS_List;
use SilverStripe\Model\List\HasManyList;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Security\Security;

/**
 * Represents a single comment object.
 *
 * @property string  $Name
 * @property string  $Comment
 * @property string  $Email
 * @property string  $URL
 * @property string  $BaseClass
 * @property boolean $Moderated
 * @property boolean $IsSpam      True if the comment is known as spam
 * @property integer $ParentID    ID of the parent page / dataobject
 * @property boolean $AllowHtml   If true, treat $Comment as HTML instead of plain text
 * @property string  $SecretToken Secret admin token required to provide moderation links between sessions
 * @property integer $Depth       Depth of this comment in the nested chain
 *
 * @method HasManyList<self> ChildComments() List of child comments
 * @method Member Author() Member object who created this comment
 * @method Comment ParentComment() Parent comment this is a reply to
 * @method DataObject|null Parent() Parent DataObject this is a reply to
 * @package comments
 */
class Comment extends DataObject
{
    private static string $table_name = 'Comment';

    private static array $db = [
        'Name' => 'Varchar(200)',
        'Comment' => 'Text',
        'Email' => 'Varchar(200)',
        'URL' => 'Varchar(255)',
        'Moderated' => 'Boolean(0)',
        'IsSpam' => 'Boolean(0)',
        'AllowHtml' => 'Boolean',
        'SecretToken' => 'Varchar(255)',
        'Depth' => 'Int'
    ];

    private static array $has_one = [
        'Author' => Member::class,
        'ParentComment' => self::class,
        'Parent' => DataObject::class
    ];

    private static array $has_many = [
        'ChildComments' => self::class
    ];

    private static array $cascade_deletes = [
        'ChildComments',
    ];

    private static string $default_sort = '"Created" DESC';

    private static array $defaults = [
        'Moderated' => 0,
        'IsSpam' => 0,
    ];

    private static array $casting = [
        'Title' => 'Varchar',
        'ParentTitle' => 'Varchar',
        'ParentClassName' => 'Varchar',
        'AuthorName' => 'Varchar',
        'RSSName' => 'Varchar',
        'DeleteLink' => 'Varchar',
        'Date' => 'Datetime',
        'SpamLink' => 'Varchar',
        'HamLink' => 'Varchar',
        'ApproveLink' => 'Varchar',
        'Permalink' => 'Varchar'
    ];

    private static array $searchable_fields = [
        'Name',
        'Email',
        'Comment',
        'Created'
    ];

    private static $summary_fields = [
        'getAuthorName' => 'Submitted By',
        'getAuthorEmail' => 'Email',
        'Comment.LimitWordCount' => 'Comment',
        'Created' => 'Date Posted',
        'Parent.Title' => 'Post',
        'IsSpam' => 'Is Spam'
    ];

    /**
     * {@inheritDoc}
     */
    private static $field_labels = [
        'Author' => 'Author Member'
    ];

    /**
     * {@inheritDoc}
     */
    public function onBeforeWrite()
    {
        parent::onBeforeWrite();

        // Sanitize HTML, because its expected to be passed to the template unescaped later
        if ($this->AllowHtml) {
            $this->Comment = $this->purifyHtml($this->Comment);
        }

        // Check comment depth
        $this->updateDepth();
    }


    /**
     * @return SecurityToken
     */
    public function getSecurityToken()
    {
        return Injector::inst()->createWithArgs(SecurityToken::class, [$this]);
    }

    /**
     * Return a link to this comment
     */
    public function Link(?string $action = ''): string
    {
        if ($parent = $this->Parent()) {
            return $parent->Link($action) . '#' . $this->Permalink();
        }

        return '';
    }

    /**
     * Returns the permalink for this {@link Comment}. Inserted into
     * the ID tag of the comment
     */
    public function Permalink(): string
    {
        $prefix = $this->getOption('comment_permalink_prefix') ?? '';

        return $prefix . $this->ID;
    }

    /**
     * Translate the form field labels for the CMS administration
     *
     * @param boolean $includerelations
     *
     * @return array
     */
    public function fieldLabels($includerelations = true)
    {
        $labels = parent::fieldLabels($includerelations);

        $labels['Name'] = _t(__CLASS__ . '.NAME', 'Author name');
        $labels['Comment'] = _t(__CLASS__ . '.COMMENT', 'Comment');
        $labels['Email'] = _t(__CLASS__ . '.EMAIL', 'Email');
        $labels['URL'] = _t(__CLASS__ . '.URL', 'URL');
        $labels['IsSpam'] = _t(__CLASS__ . '.ISSPAM', 'Spam?');
        $labels['Moderated'] = _t(__CLASS__ . '.MODERATED', 'Moderated?');
        $labels['ParentTitle'] = _t(__CLASS__ . '.PARENTTITLE', 'Parent');
        $labels['Created'] = _t(__CLASS__ . '.CREATED', 'Date posted');

        return $labels;
    }

    /**
     * Get the commenting option
     *
     * @param string $key
     *
     * @return mixed Result if the setting is available, or null otherwise
     */
    public function getOption(string $key): mixed
    {
        /** @var DataObject&CommentsExtension $record */
        $record = $this->Parent();

        if (!$record?->exists()) {
            return null;
        }

        if (!$record->hasMethod('getCommentsOption')) {
            return null;
        }

        return $record->getCommentsOption($key);
    }

    /**
     * Returns a string to help identify the parent of the comment
     */
    public function getParentTitle(): string
    {
        $parent = $this->Parent();

        if ($parent && $parent->exists()) {
            return $parent->Title ?: ($parent->ClassName . ' #' . $parent->ID);
        }

        return '';
    }

    /**
     * Comment-parent classnames obviously vary, return the parent classname
     *
     * @return string
     */
    public function getParentClassName(): string
    {
        $parent = $this->Parent();

        if (!$parent->exists()) {
            return '';
        }

        return $parent->getClassName();
    }

    /**
     * {@inheritDoc}
     */
    public function castingHelper(string $field): ?string
    {
        // Safely escape the comment
        if (in_array($field, ['EscapedComment', 'Comment'], true)) {
            return $this->AllowHtml ? 'HTMLText' : 'Text';
        }

        return parent::castingHelper($field);
    }

    /**
     * Content to be safely escaped on the frontend
     *
     * @return string
     */
    public function getEscapedComment()
    {
        return $this->Comment;
    }

    /**
     * Return whether this comment is a preview (has not been written to the db)
     *
     * @return boolean
     */
    public function isPreview()
    {
        return !$this->exists();
    }

    /**
     * @todo needs to compare to the new {@link Commenting} configuration API
     *
     * @param Member $member
     * @param array  $context
     * @return bool
     */
    public function canCreate($member = null, $context = [])
    {
        return false;
    }

    /**
     * Checks for association with a page, and {@link SiteTree->ProvidePermission}
     * flag being set to true.
     *
     * @param Member $member
     * @return Boolean
     */
    public function canView($member = null)
    {
        $member = $this->getMember($member);

        $extended = $this->extendedCan('canView', $member);
        if ($extended !== null) {
            return $extended;
        }

        if (Permission::checkMember($member, 'CMS_ACCESS_CommentAdmin')) {
            return true;
        }

        $parent = $this->Parent();

        if ($parent->exists()) {
            return $parent->canView($member)
                && $parent->hasExtension(CommentsExtension::class)
                && $parent->CommentsEnabled;
        }

        return false;
    }


    public function canModerate($member = null): bool
    {
        return Permission::checkMember($member, 'CMS_ACCESS_CommentAdmin');
    }

    /**
     * Checks if the comment can be edited.
     *
     * @param null|int|Member $member
     * @return Boolean
     */
    public function canEdit($member = null)
    {
        $member = $this->getMember($member);

        if (!$member) {
            return false;
        }

        $extended = $this->extendedCan('canEdit', $member);
        if ($extended !== null) {
            return $extended;
        }

        if (Permission::checkMember($member, 'CMS_ACCESS_CommentAdmin')) {
            return true;
        }

        $parent = $this->Parent();

        if ($parent->exists()) {
            return $parent->canEdit($member);
        }

        return false;
    }

    /**
     * Checks if the comment can be deleted.
     *
     * @param null|int|Member $member
     * @return Boolean
     */
    public function canDelete($member = null)
    {
        $member = $this->getMember($member);

        if (!$member) {
            return false;
        }

        $extended = $this->extendedCan('canDelete', $member);
        if ($extended !== null) {
            return $extended;
        }

        return $this->canEdit($member);
    }

    /**
     * Resolves Member object.
     *
     * @param Member|int|null $member
     * @return Member|null
     */
    protected function getMember($member = null)
    {
        if (is_numeric($member)) {
            $member = Member::get()->byID($member);

            if (!$member) {
                return null;
            }
        } elseif (!$member) {
            $member = Security::getCurrentUser();
        }

        return $member;
    }


    /**
     * Return the authors name for the comment.
     */
    public function getAuthorName(): string
    {
        if ($this->Name) {
            return (string) $this->Name;
        } elseif ($author = $this->Author()) {
            return (string) $author->getName();
        }

        return '';
    }


    /**
     * Return the comment authors email address.
     */
    public function getAuthorEmail(): string
    {
        if ($this->Email) {
            return (string) $this->Email;
        } elseif ($author = $this->Author()) {
            return (string) $author->Email;
        }

        return '';
    }


    /**
     * Generate a secure admin-action link authorised for the specified member
     *
     * @param string $action An action on CommentingController to link to
     * @param Member $member The member authorised to invoke this action
     *
     * @return string
     */
    protected function actionLink($action, $member = null): string
    {
        if (!$member) {
            $member = Security::getCurrentUser();
        }

        if (!$member) {
            return '';
        }

        $url = Controller::join_links(
            Director::baseURL(),
            'comments',
            $action,
            $this->ID
        );

        // Limit access for this user
        $token = $this->getSecurityToken();
        return $token->addToUrl($url, $member);
    }

    /**
     * Link to delete this comment.
     */
    public function DeleteLink(?Member $member = null): string
    {
        if ($this->canDelete($member)) {
            return $this->actionLink('delete', $member);
        }

        return '';
    }

    /**
     * Link to mark as spam.
     */
    public function SpamLink(?Member $member = null): string
    {
        if ($this->canModerate($member) && !$this->IsSpam) {
            return $this->actionLink('spam', $member);
        }

        return '';
    }


    /**
     * Link to mark as not-spam (ham)
     */
    public function HamLink(?Member $member = null): string
    {
        if ($this->canModerate($member) && $this->IsSpam) {
            return $this->actionLink('ham', $member);
        }

        return '';
    }


    /**
     * Link to approve this comment
     *
     * @param Member $member
     *
     * @return string
     */
    public function ApproveLink(?Member $member = null): string
    {
        if ($this->canModerate($member) && !$this->Moderated) {
            return $this->actionLink('approve', $member);
        }

        return '';
    }


    /**
     * Mark this comment as spam
     */
    public function markSpam(): self
    {
        $this->IsSpam = true;
        $this->Moderated = true;

        $this->write();
        $this->extend('afterMarkSpam');

        return $this;
    }

    /**
     * Mark this comment as approved
     */
    public function markApproved(): self
    {
        $this->IsSpam = false;
        $this->Moderated = true;

        $this->write();
        $this->extend('afterMarkApproved');

        return $this;
    }

    /**
     * Mark this comment as unapproved
     */
    public function markUnapproved(): self
    {
        $this->Moderated = false;
        $this->write();
        $this->extend('afterMarkUnapproved');

        return $this;
    }

    /**
     * Return the spam class for this comment.
     */
    public function SpamClass(): string
    {
        if ($this->IsSpam) {
            return 'spam';
        } elseif (!$this->Moderated) {
            return 'unmoderated';
        } else {
            return 'notspam';
        }

        return '';
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        $title = sprintf(
            _t(__CLASS__ . '.COMMENTBY', 'Comment by %s', 'Name'),
            $this->getAuthorName()
        );

        $parent = $this->Parent();

        if ($parent->exists()) {
            if ($parent->Title) {
                $title .= sprintf(' %s %s', _t(__CLASS__ . '.ON', 'on'), $parent->Title);
            }
        }

        return $title;
    }

    /*
     * Modify the default fields shown to the user
     */
    public function getCMSFields()
    {
        $commentField = $this->AllowHtml ? HTMLEditorField::class : TextareaField::class;
        $fields = FieldList::create(
            $this
                ->obj('Created')
                ->scaffoldFormField($this->fieldLabel('Created'))
                ->performReadonlyTransformation(),
            TextField::create('Name', $this->fieldLabel('Name')),
            $commentField::create('Comment', $this->fieldLabel('Comment')),
            EmailField::create('Email', $this->fieldLabel('Email')),
            TextField::create('URL', $this->fieldLabel('URL')),
            FieldGroup::create(array(
                CheckboxField::create('Moderated', $this->fieldLabel('Moderated')),
                CheckboxField::create('IsSpam', $this->fieldLabel('IsSpam')),
            ))
                ->setTitle(_t(__CLASS__ . '.OPTIONS', 'Options'))
                ->setDescription(_t(
                    __CLASS__ . '.OPTION_DESCRIPTION',
                    'Unmoderated and spam comments will not be displayed until approved'
                ))
        );

        // Show member name if given
        if (($author = $this->Author()) && $author->exists()) {
            $fields->insertAfter(
                'Name',
                TextField::create('AuthorMember', $this->fieldLabel('Author'), $author->Title)
                    ->performReadonlyTransformation()
            );
        }

        // Show parent comment if given
        if (($parent = $this->ParentComment()) && $parent->exists()) {
            $fields->push(HeaderField::create(
                'ParentComment_Title',
                _t(__CLASS__ . '.ParentComment_Title', 'This comment is a reply to the below')
            )->performReadonlyTransformation());
            // Created date
            $fields->push(DatetimeField::create('ParentComment_Created', $parent->fieldLabel('Created'))
                ->setValue($parent->Created)
                ->performReadonlyTransformation());

            // Name (could be member or string value)
            $fields->push(
                $parent
                    ->obj('AuthorName')
                    ->scaffoldFormField($parent->fieldLabel('AuthorName'))
                    ->setName('ParentComment_AuthorName')
                    ->setValue($parent->getAuthorName())
                    ->performReadonlyTransformation()
            );

            // Comment body
            $fields->push(
                $parent
                    ->obj('EscapedComment')
                    ->scaffoldFormField($parent->fieldLabel(self::class))
                    ->setName('ParentComment_EscapedComment')
                    ->setValue($parent->Comment)
                    ->performReadonlyTransformation()
            );
        }

        $this->extend('updateCMSFields', $fields);
        return $fields;
    }

    /**
     * Purify the HTML.
     */
    public function purifyHtml(string $dirtyHtml): string
    {
        if ($service = $this->getHtmlPurifierService()) {
            return $service->purify($dirtyHtml);
        }

        return $dirtyHtml;
    }

    /**
     * @return HTMLPurifier (or anything with a "purify()" method)
     */
    public function getHtmlPurifierService(): ?HTMLPurifier
    {
        if (!class_exists(HTMLPurifier_Config::class)) {
            return null;
        }

        $config = HTMLPurifier_Config::createDefault();
        $allowedElements = (array) $this->getOption('html_allowed_elements');

        if (!empty($allowedElements)) {
            $config->set('HTML.AllowedElements', $allowedElements);
        }

        // This injector cannot be set unless the 'p' element is allowed
        if (in_array('p', $allowedElements ?? [])) {
            $config->set('AutoFormat.AutoParagraph', true);
        }

        $config->set('AutoFormat.Linkify', true);
        $config->set('URI.DisableExternalResources', true);
        $config->set('Cache.SerializerPath', TempFolder::getTempFolder(BASE_PATH));

        return new HTMLPurifier($config);
    }

    /**
     * Calculate the Gravatar link from the email address
     *
     * @return string
     */
    public function Gravatar()
    {
        $gravatar = '';
        $useUserAvatar = $this->getOption('use_gravatar');

        if ($useUserAvatar) {
            $gravatar = 'https://www.gravatar.com/avatar/' . md5(strtolower(trim($this->Email ?? '')));
            $userAvatarSize = $this->getOption('gravatar_size');
            $userAvatarDefault = $this->getOption('gravatar_default');
            $userAvatarRating = $this->getOption('gravatar_rating');
            $gravatar .= '?' . http_build_query([
                's' => $userAvatarSize,
                'd' => $userAvatarDefault,
                'r' => $userAvatarRating,
            ]);
        }

        return $gravatar;
    }

    /**
     * Determine if replies are enabled for this instance
     *
     * @return boolean
     */
    public function getRepliesEnabled()
    {
        // Check reply option
        if (!$this->getOption('nested_comments')) {
            return false;
        }

        // Check if depth is limited
        $maxLevel = $this->getOption('nested_depth');
        $notSpam = ($this->SpamClass() == 'notspam');
        return $notSpam && (!$maxLevel || $this->Depth < $maxLevel);
    }

    /**
     * Proxy for checking whether the has permission to comment on the comment parent.
     */
    public function canPostComment(?Member $member = null): bool
    {
        $parent = $this->Parent();

        if (!$parent || !$parent->exists()) {
            return false;
        }

        return $parent->canPostComment($member);
    }

    /**
     * Returns the list of all replies
     *
     * @return HasManyList<self>
     */
    public function AllReplies(): ?HasManyList
    {
        // No replies if disabled
        if (!$this->getRepliesEnabled()) {
            return null;
        }

        // Get all non-spam comments
        $order = $this->getOption('order_replies_by')
            ?: $this->getOption('order_comments_by');

        /** @var HasManyList<self> $list */
        $list = $this->ChildComments()->sort($order);

        $this->extend('updateAllReplies', $list);

        return $list;
    }

    /**
     * Returns the list of replies, with spam and unmoderated items excluded, for use in the frontend
     *
     * @return SS_List
     */
    public function Replies()
    {
        // No replies if disabled
        if (!$this->getRepliesEnabled()) {
            return ArrayList::create();
        }
        $list = $this->AllReplies();

        // Filter spam comments for non-administrators if configured
        $parent = $this->Parent();

        if (!$parent) {
            return ArrayList::create();
        }

        $showSpam = $this->getOption('frontend_spam') && $parent->canModerateComments();

        if (!$showSpam) {
            $list = $list->filter('IsSpam', 0);
        }

        // Filter un-moderated comments for non-administrators if moderation is enabled
        $showUnmoderated = $parent && (
            ($parent->ModerationRequired === 'None')
            || ($this->getOption('frontend_moderation') && $parent->canModerateComments())
        );
        if (!$showUnmoderated) {
            $list = $list->filter('Moderated', 1);
        }

        $this->extend('updateReplies', $list);
        return $list;
    }

    /**
     * Returns the list of replies paged, with spam and unmoderated items excluded, for use in the frontend.
     */
    public function PagedReplies(): PaginatedList
    {
        $list = $this->Replies();

        // Add pagination
        $list = PaginatedList::create($list, Controller::curr()->getRequest());
        $list->setPaginationGetVar('repliesstart' . $this->ID);
        $list->setPageLength($this->getOption('comments_per_page'));

        $this->extend('updatePagedReplies', $list);
        return $list;
    }

    /**
     * Generate a reply form for this comment
     */
    public function ReplyForm(): ?Form
    {
        // Ensure replies are enabled
        if (!$this->getRepliesEnabled()) {
            return null;
        }

        // Check parent is available
        $parent = $this->Parent();

        if (!$parent->exists()) {
            return null;
        }

        // Build reply controller
        $controller = CommentingController::create();
        $controller->setOwnerRecord($parent);
        $controller->setParentClass($parent->ClassName);
        $controller->setOwnerController(Controller::curr());

        return $controller->ReplyForm($this);
    }

    /**
     * @return string
     */
    public function getDate()
    {
        return $this->Created;
    }

    /**
     * Refresh of this comment in the hierarchy
     */
    public function updateDepth()
    {
        $parent = $this->ParentComment();

        if ($parent->exists()) {
            $parent->updateDepth();

            $this->Depth = $parent->Depth + 1;
        } else {
            $this->Depth = 1;
        }
    }
}
