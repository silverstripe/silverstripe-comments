<% if $MoreThanOnePage %>
	<div class="comments-pagination">
		<p>
			<% if $PrevLink %>
				<a href="$PrevLink.ATT" class="previous">&laquo; <% _t('CommentsInterface_ss.PREV','previous') %></a>
			<% end_if %>

			<% if $Pages %><% loop $Pages %>
				<% if $CurrentBool %>
					<strong>$PageNum</strong>
				<% else %>
					<a href="$Link.ATT">$PageNum</a>
				<% end_if %>
			<% end_loop %><% end_if %>

			<% if $NextLink %>
				<a href="$NextLink.ATT" class="next"><% _t('CommentsInterface_ss.NEXT','next') %> &raquo;</a>
			<% end_if %>
		</p>
	</div>
<% end_if %>
