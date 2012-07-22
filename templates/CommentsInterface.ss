<% if CommentsEnabled %>
	<div id="$CommentHolderID" class="comments-holder-container">

		<h4><% _t('POSTCOM','Post your comment') %></h4>
		
		<% if AddCommentForm %>
			<% if CanPost %>
				$AddCommentForm
			<% else %>
				<p><% _t('COMMENTLOGINERROR', 'You cannot post comments until you have logged in') %><% if PostingRequiresPermission %>,<% _t('COMMENTPERMISSIONERROR', 'and that you have an appropriate permission level') %><% end_if %>. 
					<a href="Security/login?BackURL={$Page.Link}" title="<% _t('LOGINTOPOSTCOMMENT', 'Login to post a comment') %>"><% _t('COMMENTPOSTLOGIN', 'Login Here') %></a>.
				</p>
			<% end_if %>
		<% else %>
			<p><% _t('COMMENTSDISABLED', 'Posting comments has been disabled') %>.</p>	
		<% end_if %>

		<h4><% _t('COMMENTS','Comments') %></h4>
	
		<div class="comments-holder">
			<% if Comments %>
				<ul class="comments-list">
					<% control Comments %>
						<li class="comment $EvenOdd<% if FirstLast %> $FirstLast <% end_if %> $SpamClass">
							<% include CommentsInterface_singlecomment %>
						</li>
					<% end_control %>
				</ul>
			
				<% if Comments.MoreThanOnePage %>
					<div class="comments-pagination">
						<p>
							<% if Comments.PrevLink %>
								<a href="$Comments.PrevLink" class="previous">&laquo; <% _t('PREV','previous') %></a>
							<% end_if %>
					
							<% if Comments.Pages %>
								<% loop Comments.Pages %>
									<% if CurrentBool %>
										<strong>$PageNum</strong>
									<% else %>
										<a href="$Link">$PageNum</a>
									<% end_if %>
								<% end_loop %>
							<% end_if %>
	
							<% if Comments.NextLink %>
								<a href="$Comments.NextLink" class="next"><% _t('NEXT','next') %> &raquo;</a>
							<% end_if %>
						</p>
					</div>
				<% end_if %>
			<% else %>
				<p class="no-comments-yet"><% _t('NOCOMMENTSYET','No one has commented on this page yet.') %></p>
			<% end_if %>
		</div>
		
		<% if DeleteAllLink %>
			<p class="delete-comments">
				<a href="$DeleteAllLink"><% _t('PageCommentInterface.DELETEALLCOMMENTS','Delete all comments on this page') %></a>
			</p>
		<% end_if %>

		<p class="commenting-rss-feed">
			<a href="$RssLinkPage"><% _t('RSSFEEDCOMMENTS', 'RSS feed for comments on this page') %></a> | 
			<a href="$RssLink"><% _t('RSSFEEDALLCOMMENTS', 'RSS feed for all comments') %></a>
		</p>
	</div>
<% end_if %>
	
