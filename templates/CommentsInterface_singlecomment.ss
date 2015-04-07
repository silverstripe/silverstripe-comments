<% if not $isPreview %>
	<p class="info">
		<% if $URL %>
			<a class="author" href="$URL.URL" rel="nofollow">$AuthorName.XML</a>
		<% else %>
			<span class="author">$AuthorName.XML</span>
		<% end_if %>
		<span class="date">$Created.Nice ($Created.Ago)</span>
		<% if $Gravatar %>
			<img class="gravatar" src="$Gravatar.ATT" alt="Gravatar for $Name.ATT" title="Gravatar for $Name.ATT" />
		<% end_if %>
	</p>
<% end_if %>

<div class="comment-text" id="<% if $isPreview %>comment-preview<% else %>$Permalink<% end_if %>">
	<p>$EscapedComment</p>
</div>

<% if not $isPreview %>
	<% if $ApproveLink || $SpamLink || $HamLink || $DeleteLink || $RepliesEnabled %>
		<ul class="action-links">
			<% if $ApproveLink %>
				<li><a href="$ApproveLink.ATT" class="approve"><% _t('CommentsInterface_singlecomment_ss.APPROVE', 'approve this comment') %></a></li>
			<% end_if %>
			<% if $SpamLink %>
				<li><a href="$SpamLink.ATT" class="spam"><% _t('CommentsInterface_singlecomment_ss.ISSPAM','this comment is spam') %></a></li>
			<% end_if %>
			<% if $HamLink %>
				<li><a href="$HamLink.ATT" class="ham"><% _t('CommentsInterface_singlecomment_ss.ISNTSPAM','this comment is not spam') %></a></li>
			<% end_if %>
			<% if $DeleteLink %>
				<li><a href="$DeleteLink.ATT" class="delete"><% _t('CommentsInterface_singlecomment_ss.REMCOM','remove this comment') %></a></li>
			<% end_if %>
			<% if $RepliesEnabled %>
				<li class="comment-reply-action">
					<a class="comment-reply-link" href="#{$ReplyForm.FormName}">Reply to $AuthorName.XML</a>
				</li>
			<% end_if %>
		</ul>
	<% end_if %>
	
	<% include CommentReplies %>
<% end_if %>
