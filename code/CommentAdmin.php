<?php

/**
 * Comment administration system within the CMS
 *
 * @package comments
 */
class CommentAdmin extends LeftAndMain {

	static $url_segment = 'comments';

	static $url_rule = '/$Action';

	static $menu_title = 'Comments';

	static $template_path = null; // defaults to (project)/templates/email

	static $allowed_actions = array(
		'approvedmarked',
		'deleteall',
		'deletemarked',
		'hammarked',
		'showtable',
		'spammarked',
		'EditForm',
		'unmoderated'
	);
	
	/**
	 * @var int The number of comments per page for the {@link CommentTable} in this admin.
	 */
	static $comments_per_page = 20;

	public function init() {
		parent::init();

		//Requirements::javascript(CMS_DIR . '/javascript/CommentAdmin_right.js');
		//Requirements::css(CMS_DIR . '/css/CommentAdmin.css');
	}

	public function getEditForm($id = null, $fields = null) {
		// TODO Duplicate record fetching (see parent implementation)
		if(!$id) $id = $this->currentPageID();
		$form = parent::getEditForm($id);
		
		// TODO Duplicate record fetching (see parent implementation)
		$record = $this->getRecord($id);
		if($record && !$record->canView()) return Security::permissionFailure($this);
		
		$commentList = GridField::create(
			'Comments',
			false,
			Comment::get(),
			$commentListConfig = GridFieldConfig_RecordViewer::create()
				//->addComponent(new GridFieldExportButton())
		)->addExtraClass("comments_grid");
		//$commentListConfig->getComponentByType('GridFieldDetailForm')->setValidator(new Comment_Validator());
		
		$fields = new FieldList(
			$root = new TabSet(
				'Root',
				$commentsTab = new Tab('Comments', _t('CommentAdmin.Comments', 'Comments'),
					$commentList// ,
					// new HeaderField(_t('CommentAdmin.IMPORTCOMMENTS', 'Import comments'), 3),
					// new LiteralField(
					// 	'CommentImportFormIframe',
					// 	sprintf(
					// 		'<iframe src="%s" id="CommentImportFormIframe" width="100%%" height="250px" border="0"></iframe>',
					// 		$this->Link('commentimport')
					// 	)
					// )
				)
			),
			// necessary for tree node selection in LeftAndMain.EditForm.js
			new HiddenField('ID', false, 0)
		);
		
		$root->setTemplate('CMSTabSet');

		$actions = new FieldList();
		
		$form = new Form(
			$this,
			'EditForm',
			$fields,
			$actions
		);
		$form->addExtraClass('cms-edit-form');
		$form->setTemplate($this->getTemplatesWithSuffix('_EditForm'));
		if($form->Fields()->hasTabset()) $form->Fields()->findOrMakeTab('Root')->setTemplate('CMSTabSet');
		$form->addExtraClass('center ss-tabset cms-tabset ' . $this->BaseCSSClasses());

		$this->extend('updateEditForm', $form);

		return $form;
	}

	public function showtable($params) {
	    return $this->getLastFormIn($this->renderWith('CommentAdmin_right'));
	}

	public function Section() {
		$url = rtrim($_SERVER['REQUEST_URI'], '/');
		if(strrpos($url, '&')) {
			$url = substr($url, 0, strrpos($url, '&'));
		}
		$section = substr($url, strrpos($url, '/') + 1);

		if($section != 'approved' && $section != 'unmoderated' && $section != 'spam') {
			$section = Session::get('CommentsSection');
		}

		if($section != 'approved' && $section != 'unmoderated' && $section != 'spam') {
			$section = 'approved';
		}

		return $section;
	}


	function deletemarked() {
		$numComments = 0;
		$folderID = 0;
		$deleteList = '';

		if($_REQUEST['Comments']) {
			foreach($_REQUEST['Comments'] as $commentid) {
				$comment = DataObject::get_by_id('Comment', $commentid);
				if($comment && $comment->canDelete()) {
					$comment->delete();
					$numComments++;
				}
			}
		} else {
			user_error("No comments in $commentList could be found!", E_USER_ERROR);
		}

		echo <<<JS
			$deleteList
			$('Form_EditForm').getPageFromServer($('Form_EditForm_ID').value);
			statusMessage("Deleted $numComments comments.");
JS;
	}

	function deleteall() {
		$numComments = 0;
		$spam = DataObject::get('Comment', "\"Comment\".\"IsSpam\" = '1'");

		if($spam) {
			$numComments = $spam->Count();

			foreach($spam as $comment) {
				if($comment->canDelete()) {
					$comment->delete();
				}
			}
		}

		$msg = sprintf(_t('CommentAdmin.DELETED', 'Deleted %s comments.'), $numComments);
		echo <<<JS
				$('Form_EditForm').getPageFromServer($('Form_EditForm_ID').value);
				statusMessage("$msg");
JS;

	}

	function spammarked() {
		$numComments = 0;
		$folderID = 0;
		$deleteList = '';

		if($_REQUEST['Comments']) {
			foreach($_REQUEST['Comments'] as $commentid) {
				$comment = DataObject::get_by_id('Comment', $commentid);
				if($comment) {
					$comment->IsSpam = true;
					$comment->Moderated = true;
					$comment->write();

					$numComments++;
				}
			}
		} else {
			user_error("No comments in $commentList could be found!", E_USER_ERROR);
		}

		$msg = sprintf(_t('CommentAdmin.MARKEDSPAM', 'Marked %s comments as spam.'), $numComments);
		echo <<<JS
			$deleteList
			$('Form_EditForm').getPageFromServer($('Form_EditForm_ID').value);
			statusMessage("$msg");
JS;
	}

	function hammarked() {
		$numComments = 0;
		$folderID = 0;
		$deleteList = '';

		if($_REQUEST['Comments']) {
			foreach($_REQUEST['Comments'] as $commentid) {
				$comment = DataObject::get_by_id('Comment', $commentid);

				if($comment) {
					$comment->IsSpam = false;
					$comment->Moderated = true;
					$comment->write();

					$numComments++;
				}
			}
		} else {
			user_error("No comments in $commentList could be found!", E_USER_ERROR);
		}

		$msg = sprintf(_t('CommentAdmin.MARKEDNOTSPAM', 'Marked %s comments as not spam.'), $numComments);
		echo <<<JS
			$deleteList
			$('Form_EditForm').getPageFromServer($('Form_EditForm_ID').value);
			statusMessage("$msg");
JS;
	}

	function acceptmarked() {
		$numComments = 0;
		$folderID = 0;
		$deleteList = '';

		if($_REQUEST['Comments']) {
			foreach($_REQUEST['Comments'] as $commentid) {
				$comment = DataObject::get_by_id('Comment', $commentid);
				if($comment) {
					$comment->IsSpam = false;
					$comment->Moderated = true;
					$comment->write();
					$numComments++;
				}
			}
		} else {
			user_error("No comments in $commentList could be found!", E_USER_ERROR);
		}

		$msg = sprintf(_t('CommentAdmin.APPROVED', 'Accepted %s comments.'), $numComments);
		echo <<<JS
			$deleteList
			$('Form_EditForm').getPageFromServer($('Form_EditForm_ID').value);
			statusMessage("Accepted $numComments comments.");
JS;
	}

	/**
	 * Return the number of moderated comments
	 *
	 * @return int
	 */
	function NumModerated() {
		return DB::query("SELECT COUNT(*) FROM \"Comment\" WHERE \"Moderated\" = 1")->value();
	}

	/**
	 * Return the number of unmoderated comments
	 *
	 * @return int
	 */
	function NumUnmoderated() {
		return DB::query("SELECT COUNT(*) FROM \"Comment\" WHERE \"Moderated\" = 0")->value();
	}

	/**
	 * Return the number of comments marked as spam
	 *
	 * @return int
	 */
	function NumSpam() {
		return DB::query("SELECT COUNT(*) FROM \"Comment\" WHERE \"IsSpam\" = 1")->value();
	}
	
	/**
	 * @param int
	 */	
	function set_comments_per_page($num){
		self::$comments_per_page = $num;
	}
	
	/**
	 * @return int
	 */
	function get_comments_per_page(){
		return self::$comments_per_page;
	}
}
