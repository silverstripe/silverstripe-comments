<% if AddCommentForm %>
	<% if CanPost %>
		<% if ModeratedSubmitted %>
			<p id="$CommentHolderID_PostCommentForm_error" class="message good"><% _t('AWAITINGMODERATION', 'Your comment has been submitted and is now awaiting moderation.') %></p>
		<% end_if %>
		$AddCommentForm
	<% else %>
		<p><% _t('COMMENTLOGINERROR', 'You cannot post comments until you have logged in') %><% if PostingRequiresPermission %>,<% _t('COMMENTPERMISSIONERROR', 'and that you have an appropriate permission level') %><% end_if %>. 
			<a href="Security/login?BackURL={$Parent.Link}" title="<% _t('LOGINTOPOSTCOMMENT', 'Login to post a comment') %>"><% _t('COMMENTPOSTLOGIN', 'Login Here') %></a>.
		</p>
	<% end_if %>
<% else %>
	<p><% _t('COMMENTSDISABLED', 'Posting comments has been disabled') %>.</p>	
<% end_if %>