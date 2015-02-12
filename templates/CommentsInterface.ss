<% if CommentsEnabled %>
	<%	require javascript('comments/javascript/commenting.js') %>
	<%  require css('comments/css/comments.css') %>
	<div id="$CommentHolderID" class="comments-holder-container">
		<h4 id="postYourCommentHeader"><% _t('CommentsInterface_ss.POSTCOM','Post your comment') %></h4>
		
		<div id="CommentsFormContainer"><% include CommentsInterfaceForm %></div>

		<h4 class="comments-heading"><% _t('CommentsInterface_ss.COMMENTS','Comments') %></h4>
	
		<div class="comments-holder">
			<% if Comments %>
				<ul class="comments-list">
					<% loop Comments %>
						<li class="depth{$Depth} comment $EvenOdd<% if FirstLast %> $FirstLast <% end_if %> $SpamClass">
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
	
