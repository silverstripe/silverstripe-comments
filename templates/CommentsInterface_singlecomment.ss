<div class="comment" data-comment-id="$ID" id="<% if isPreview %>comment-preview<% else %>$Permalink<% end_if %>">
<% if MarkedAsDeleted %><% _t('DELETED_BY_ADMIN','This comment was deleted by an administrator') %><% else %>
<% if $Gravatar %><div class="gravatarContainer"><img class="gravatar" src="$Gravatar" alt="Gravatar for $Name" title="Gravatar for $Name" /></div><% end_if %>
<div class="actualcomment">
<% if $URL %>
	<h4><% _t('CommentsInterface_singlecomment_ss.PBY','Posted by') %> <a href="$URL.URL" rel="nofollow">$AuthorName.XML</a></h4>
	<% else %>
	<h4><% _t('CommentsInterface_singlecomment_ss.PBY','Posted by') %> $AuthorName.XML</h4>
	<% end_if %>
	<div class="date">$Created.Nice ($Created.Ago)</div>
	<% if TwitterUsername %><div><span class="twitterFollowButton"><a href="http://www.twitter.com/{$TwitterUsername}" class="twitter-follow-button" data-show-count="true">$TwitterUsername</a></span></div><% end_if %>
$EscapedComment

<% if $CanReply %><ul class="no-bullet replyButtonContainer">
	<li class="replyButton"><button type="button"  class="btn btn-primary small radius" data-comment-to-reply-to-id="$ID">Reply</button></li>
	<li class="cancelReplyButton hidden"><button type="button" class="btn btn-primary small radius" data-comment-to-reply-to-id="$ID">Cancel Reply</button></li>
</ul><% end_if %>

<% if $ApproveLink || $SpamLink || $HamLink || $DeleteLink %>
			<ul class="action-links">
				<% if ApproveLink %>
					<li><a href="$ApproveLink.ATT" class="approve"><% _t('CommentsInterface_singlecomment_ss.APPROVE', 'approve this comment') %></a></li>
				<% end_if %>
				<% if SpamLink %>
					<li><a href="$SpamLink.ATT" class="spam"><% _t('CommentsInterface_singlecomment_ss.ISSPAM','this comment is spam') %></a></li>
				<% end_if %>
				<% if HamLink %>
					<li><a href="$HamLink.ATT" class="ham"><% _t('CommentsInterface_singlecomment_ss.ISNTSPAM','this comment is not spam') %></a></li>
				<% end_if %>
				<% if DeleteLink %>
					<li class="last"><a href="$DeleteLink.ATT" class="delete"><% _t('CommentsInterface_singlecomment_ss.REMCOM','remove this comment') %></a></li>
				<% end_if %>
			</ul>
		<% end_if %>
</div>
<% end_if %>
</div>