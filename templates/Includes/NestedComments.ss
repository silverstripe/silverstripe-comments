
<a class="replycomment" data-id="$ID" href="CommentingController/replyform/$ID"><% _t('REPLY', 'reply') %></a>
<div class="replycommentformholder" data-id="$ID">
</div>

<% if $Comments %>
	<ul class="nested-comments-list">
		<% loop $Comments %>
			<li class="comment $EvenOdd<% if FirstLast %> $FirstLast <% end_if %> $SpamClass">
				<% include CommentsInterface_singlecomment %>
			</li>
		<% end_loop %>
	</ul>
<% end_if %>
