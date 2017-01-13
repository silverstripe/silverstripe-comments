<% if $RepliesEnabled %>
	<div class="comment-replies-container">
		
		<div class="comment-reply-form-holder">
			$ReplyForm
		</div>
	
		<div class="comment-replies-holder">
			<% if $PagedReplies %>
				<ul class="comments-list level-{$Depth}">
					<% loop $PagedReplies %>
						<li class="comment $EvenOdd<% if FirstLast %> $FirstLast <% end_if %> $SpamClass">
							<% include CommentsInterface_singlecomment %>
						</li>
					<% end_loop %>
				</ul>
				<% with $PagedReplies %>
					<% include ReplyPagination %>
				<% end_with %>
			<% end_if %>
		</div>
	</div>
<% end_if %>
