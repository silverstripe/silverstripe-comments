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
		$this->markTestIncomplete("TODO");
	}	
}