# TODO

 * Permissions and configuration needs to be set on a per type of owner class rather than global
   so we can support a blog with public comments and an ecommerce store with comments that you 
   have to login to post for instance
	
	Commenting::enable_comments('SiteTree', array(
		'requires_permission' 	=> FOO,
		'requires_login'		=> false
	));
	
 * Merge simon_w's jQuery work for page comments back in.

 * Tests