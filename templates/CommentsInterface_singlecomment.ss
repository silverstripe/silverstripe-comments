<div class="comment" id="$Permalink">
	<p>$Comment.XML</p>
</div>

<p class="info">
	<% if $URL %>
		<% _t('PBY','Posted by') %> <a href="$URL.URL" rel="nofollow">$AuthorName.XML</a>, $Created.Nice ($Created.Ago)
	<% else %>
		<% _t('PBY','Posted by') %> $AuthorName.XML, $Created.Nice ($Created.Ago)
	<% end_if %>
</p>

<% if $ApproveLink || $SpamLink || $HamLink || $DeleteLink %>
	<ul class="action-links">
		<% if ApproveLink %>
			<li><a href="$ApproveLink.ATT" class="approve"><% _t('APPROVE', 'approve this comment') %></a></li>
		<% end_if %>
		<% if SpamLink %>
			<li><a href="$SpamLink.ATT" class="spam"><% _t('ISSPAM','this comment is spam') %></a></li>
		<% end_if %>
		<% if HamLink %>
			<li><a href="$HamLink.ATT" class="ham"><% _t('ISNTSPAM','this comment is not spam') %></a></li>
		<% end_if %>
		<% if DeleteLink %>
			<li class="last"><a href="$DeleteLink.ATT" class="delete"><% _t('REMCOM','remove this comment') %></a></li>
		<% end_if %>
	</ul>
<% end_if %>
