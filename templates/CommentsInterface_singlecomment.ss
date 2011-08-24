<p class="comment" id="$Permalink">
	<% if bbCodeEnabled %>
		$ParsedBBCode	
	<% else %>
		$Comment.XML	
	<% end_if %>
</p>
<p class="info">
	<% if CommenterURL %>
		<% _t('PBY','Posted by') %> <a href="$CommenterURL.ATT" rel="nofollow">$Name.XML</a>, $Created.Nice ($Created.Ago)
	<% else %>
		<% _t('PBY','Posted by') %> $Name.XML, $Created.Nice ($Created.Ago)
	<% end_if %>
</p>

<ul class="action-links">
	<% if ApproveLink %>
		<li><a href="$ApproveLink" class="approve"><% _t('APPROVE', 'approve this comment') %></a></li>
	<% end_if %>
	<% if SpamLink %>
		<li><a href="$SpamLink" class="spam"><% _t('ISSPAM','this comment is spam') %></a></li>
	<% end_if %>
	<% if HamLink %>
		<li><a href="$HamLink" class="ham"><% _t('ISNTSPAM','this comment is not spam') %></a></li>
	<% end_if %>
	<% if DeleteLink %>
		<li class="last"><a href="$DeleteLink" class="delete"><% _t('REMCOM','remove this comment') %></a></li>
	<% end_if %>
</ul>
