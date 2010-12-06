<?php
/**
 * The SSAkismet class provides spam detection for comments using http://akismet.com/. 
 * In order to use it, you must get an API key, which you can get free for non-commercial use by signing 
 * up for a http://www.wordpress.com account. Commercial keys can be bought at http://akismet.com/commercial/.
 * 
 * To enable spam detection, set your API key in _config.php.  
 * The following lines should be added to **mysite/_config.php** 
 * (or to the _config.php in another folder if you're not using mysite). 
 * 
 * <code>
 * SSAkismet::setAPIKey('<your-key>');
 * </code>
 * 
 * You can then view spam for a page by appending ?showspam=1 to the url, or use the {@link CommentAdmin} in the CMS.
 * 
 * @see http://demo.silverstripe.com/blog Demo of SSAkismet in action
 * 
 * @package comments
 */
class SSAkismet extends Akismet {

	private static $apiKey;

	private static $saveSpam = true;
	
	static function setAPIKey($key) {
		self::$apiKey = $key;
	}
	
	static function isEnabled() {
		return (self::$apiKey != null) ? true : false;
	}
	
	static function setSaveSpam($save = true) {
		SSAkismet::$saveSpam = $save;
	}
	
	static function getSaveSpam() {
		return SSAkismet::$saveSpam;
	}
	
	public function __construct() {
		parent::__construct(Director::absoluteBaseURL(), self::$apiKey);
	}
}

/**
 * Adds the hooks required into posting a comment to check for spam
 *
 * @package comments
 */
class SSAkismetExtension extends Extension {
	
	function onBeforePostComment(&$form) {
		$data = $form->getData();
		
		if(SSAkismet::isEnabled()) {
			try {
				$akismet = new SSAkismet();

				$akismet->setCommentAuthor($data['Name']);
				$akismet->setCommentContent($data['Comment']);

				if($akismet->isCommentSpam()) {
					if(SSAkismet::getSaveSpam()) {
						$comment = Object::create('Comment');
						$form->saveInto($comment);
						$comment->setField("IsSpam", true);
						$comment->write();
					}
					echo "<b>"._t('CommentingController.SPAMDETECTED', 'Spam detected!!') . "</b><br /><br />";
					printf("If you believe this was in error, please email %s.", ereg_replace("@", " _(at)_", Email::getAdminEmail()));
					echo "<br /><br />"._t('CommentingController.MSGYOUPOSTED', 'The message you posted was:'). "<br /><br />";
					echo $data['Comment'];

					return;
				}
			} catch (Exception $e) {
				// Akismet didn't work, continue without spam check
			}
		}
	}
}