<?php 

class CommentTwitterUsernameExtension extends DataExtension {
	// maximum length of a twitter usnername is 15 chars
	private static $db = array('TwitterUsername'=>'Varchar(16)');

	
}