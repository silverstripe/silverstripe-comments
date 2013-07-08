<div class="comment" id="<% if isPreview %>comment-preview<% else %>$Permalink<% end_if %>">
<% if $Gravatar %><img class="gravatar" src="$Gravatar" alt="Gravatar for $Name" title="Gravatar for $Name" /><% end_if %>
	$EscapedComment
</div>

<% if not isPreview %>
	<p class="info">
		<% if $URL %>
			<% _t('CommentsInterface_singlecomment_ss.PBY','Posted by') %> <a href="$URL.URL" rel="nofollow">$AuthorName.XML</a>, $Created.Nice ($Created.Ago)
		<% else %>
			<% _t('CommentsInterface_singlecomment_ss.PBY','Posted by') %> $AuthorName.XML, $Created.Nice ($Created.Ago)
		<% end_if %>
	</p>

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
<% end_if %>
