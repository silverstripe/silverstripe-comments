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
<div class="comment-text<% if $Gravatar %> hasGravatar<% end_if %>" id="<% if $isPreview %>comment-preview<% else %>{$Permalink}-text<% end_if %>">
	<p>$EscapedComment</p>
</div>
<% if $UpdateForm %>
<div class="comment-update-form-holder">
  $UpdateForm
</div>
<% end_if %>

<% if not $isPreview %>
	<% if $ApproveLink || $SpamLink || $HamLink || $DeleteLink || $UpdateForm || $RepliesEnabled %>
		<div class="comment-action-links">
			<div class="comment-moderation-options">
				<% if $ApproveLink %>
					<a href="$ApproveLink.ATT" class="approve"><%t CommentsInterface_singlecomment_ss.APPROVE "Approve it" %></a>
				<% end_if %>
				<% if $SpamLink %>
					<a href="$SpamLink.ATT" class="spam"><%t CommentsInterface_singlecomment_ss.ISSPAM "Spam it" %></a>
				<% end_if %>
				<% if $HamLink %>
					<a href="$HamLink.ATT" class="ham"><%t CommentsInterface_singlecomment_ss.ISNTSPAM "Not spam" %></a>
				<% end_if %>
				<% if $DeleteLink %>
					<a href="$DeleteLink.ATT" class="delete"><%t CommentsInterface_singlecomment_ss.REMCOM "Reject it" %></a>
				<% end_if %>
			</div>
			<div class="comment-author-options">
				<% if $UpdateForm %>
					<button class="comment-update-link" aria-controls="$UpdateForm.FormName" aria-expanded="false"><%t CommentsInterface_singlecomment_ss.UPDCOM "Update it" %></button>
				<% end_if %>
			</div>
			<% if $RepliesEnabled && $canPostComment %>
				<button class="comment-reply-link" type="button" aria-controls="$ReplyForm.FormName" aria-expanded="false">
					<%t CommentsInterface_singlecomment_ss.REPLYTO "Reply to" %> $AuthorName.XML
				</button>
			<% end_if %>
		</div>
	<% end_if %>

	<% include CommentReplies %>
<% end_if %>
