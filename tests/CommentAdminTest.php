<?php

/**
 * @package comments
 */

class CommentAdminTest extends FunctionalTest {
	
	static $fixture_file = 'comments/tests/CommentsTest.yml';
	
	function testNumModerated() {
		$comm = new CommentAdmin();
		$resp = $comm->NumModerated();
		$this->assertEquals(4, $resp);
	}
	
	function testNumUnmoderated(){
		$comm = new CommentAdmin();
		$resp = $comm->NumUnmoderated();
		$this->assertEquals(3, $resp);
	}
	
	function testNumSpam(){
		$comm = new CommentAdmin();
		$resp = $comm->NumSpam();
		$this->assertEquals(2, $resp);
	}
	
	function testdeletemarked(){
		$comm = $this->objFromFixture('Comment', 'firstComA');
		$id = $comm->ID;
		$this->logInWithPermission('CMS_ACCESS_CommentAdmin');
		$result = $this->get("admin/comments/EditForm/field/Comments/item/$id/delete");
		
		$checkComm = DataObject::get_by_id('Comment',$id);

		$this->assertFalse($checkComm);
	}	
}