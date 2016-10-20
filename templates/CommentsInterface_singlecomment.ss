<% if not $isPreview %>
	<p class="info" id="$Permalink">
		<% if $URL %>
			<a class="author" href="$URL.URL" rel="nofollow">$AuthorName.XML</a>
		<% else %>
			<span class="author">$AuthorName.XML</span>
		<% end_if %>
		<span class="date">$Created.Nice ($Created.Ago)</span>
	</p>
<% end_if %>
<% if $Gravatar %>
    <img class="gravatar" src="$Gravatar.ATT" alt="Gravatar for $Name.ATT" title="Gravatar for $Name.ATT" />
<% end_if %>
<div class="comment-text<% if $Gravatar %> hasGravatar<% end_if %>" id="<% if $isPreview %>comment-preview<% else %>$Permalink<% end_if %>">
	<p>$EscapedComment</p>
</div>

<% if not $isPreview %>
	<% if $ApproveLink || $SpamLink || $HamLink || $DeleteLink || $RepliesEnabled %>
		<div class="comment-action-links">
			<div class="comment-moderation-options">
				<% if $ApproveLink %>
					<a href="$ApproveLink.ATT" class="approve"><% _t('CommentsInterface_singlecomment_ss.APPROVE', 'approve it') %></a>
				<% end_if %>
				<% if $SpamLink %>
					<a href="$SpamLink.ATT" class="spam"><% _t('CommentsInterface_singlecomment_ss.ISSPAM','spam it') %></a>
				<% end_if %>
				<% if $HamLink %>
					<a href="$HamLink.ATT" class="ham"><% _t('CommentsInterface_singlecomment_ss.ISNTSPAM','not spam') %></a>
				<% end_if %>
				<% if $DeleteLink %>
					<a href="$DeleteLink.ATT" class="delete"><% _t('CommentsInterface_singlecomment_ss.REMCOM','reject it') %></a>
				<% end_if %>
			</div>
			<% if $RepliesEnabled %>
				<a class="comment-reply-link" href="#{$ReplyForm.FormName}">Reply to $AuthorName.XML</a>
			<% end_if %>
		</div>
	<% end_if %>

	<% include CommentReplies %>
<% end_if %>
