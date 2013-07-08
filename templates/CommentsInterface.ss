<% if CommentsEnabled %>
	<div id="$CommentHolderID" class="comments-holder-container">
		<h4><% _t('CommentsInterface_ss.POSTCOM','Post your comment') %></h4>
		
		<% if AddCommentForm %>
			<% if CanPost %>
				<% if ModeratedSubmitted %>
					<p id="$CommentHolderID_PostCommentForm_error" class="message good"><% _t('CommentsInterface_ss.AWAITINGMODERATION', 'Your comment has been submitted and is now awaiting moderation.') %></p>
				<% end_if %>
				$AddCommentForm
			<% else %>
				<p><% _t('CommentsInterface_ss.COMMENTLOGINERROR', 'You cannot post comments until you have logged in') %><% if PostingRequiresPermission %>,<% _t('CommentsInterface_ss.COMMENTPERMISSIONERROR', 'and that you have an appropriate permission level') %><% end_if %>. 
					<a href="Security/login?BackURL={$Parent.Link}" title="<% _t('CommentsInterface_ss.LOGINTOPOSTCOMMENT', 'Login to post a comment') %>"><% _t('CommentsInterface_ss.COMMENTPOSTLOGIN', 'Login Here') %></a>.
				</p>
			<% end_if %>
		<% else %>
			<p><% _t('CommentsInterface_ss.COMMENTSDISABLED', 'Posting comments has been disabled') %>.</p>	
		<% end_if %>

		<h4><% _t('CommentsInterface_ss.COMMENTS','Comments') %></h4>
	
		<div class="comments-holder">
			<% if Comments %>
				<ul class="comments-list">
					<% loop Comments %>
						<li class="comment $EvenOdd<% if FirstLast %> $FirstLast <% end_if %> $SpamClass">
							<% include CommentsInterface_singlecomment %>
						</li>
					<% end_loop %>
				</ul>
			
				<% if Comments.MoreThanOnePage %>
					<div class="comments-pagination">
						<p>
							<% if Comments.PrevLink %>
								<a href="$Comments.PrevLink" class="previous">&laquo; <% _t('CommentsInterface_ss.PREV','previous') %></a>
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
								<a href="$Comments.NextLink" class="next"><% _t('CommentsInterface_ss.NEXT','next') %> &raquo;</a>
							<% end_if %>
						</p>
					</div>
				<% end_if %>
			<% end_if %>

			<p class="no-comments-yet"<% if $Comments.Count %> style='display: none' <% end_if %> ><% _t('CommentsInterface_ss.NOCOMMENTSYET','No one has commented on this page yet.') %></p>

		</div>
		
		<% if DeleteAllLink %>
			<p class="delete-comments">
				<a href="$DeleteAllLink"><% _t('CommentsInterface_ss.PageCommentInterface.DELETEALLCOMMENTS','Delete all comments on this page') %></a>
			</p>
		<% end_if %>

		<p class="commenting-rss-feed">
			<a href="$RssLinkPage"><% _t('CommentsInterface_ss.RSSFEEDCOMMENTS', 'RSS feed for comments on this page') %></a> | 
			<a href="$RssLink"><% _t('CommentsInterface_ss.RSSFEEDALLCOMMENTS', 'RSS feed for all comments') %></a>
		</p>
	</div>
<% end_if %>
	
