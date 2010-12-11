/**
 * @package userforms
 */

(function($) {
	$(document).ready(function () {
		
		/**
		 * Please note this functionality has not been finished
		 * this file is not loaded on your site. It is simply here
		 * to provide a starting point for someone to take it over
		 *
		 * @todo finish
		 */
		
		return false;
		
		
		
		
		
		
		
		$('.comments-holder-container form').submit(function (e) {

			if($('.comment-holder form [name=Name]').val() && $('.comment-holder form [name=Comment]').val()) {
				// remove the no comments posted text
				if($('.no-comments-yet').length > 0) {
					$('.no-comments-yet').remove();
					
					$(this).parents(".comments-holder-container")
						.find(".comments-holder")
						.append("<ul class='comments-list'></ul>");
				}
				
				error.hide();
				$('#PageComments').prepend('<li><p><img src="cms/images/network-save.gif" /> Loading...</p></li>');
				newComment = $('#PageComments').children()[0];
				newComment = $(newComment);
				
				$(this).ajaxSubmit(function(response) {
					if($('#PageComments_holder [name=Math]').length > 0) {
						$.ajax({
							url: jQuery('base').attr('href') + 'PageCommentInterface_Controller/newspamquestion',
							cache: false,
							success: function(html){
								// Load spam stuff goes in here
								$('#PageComments_holder #Math label').text(html);
								$('#PageComments_holder #Math input').val('');
							},
							failure: function(html) {
								eval(html);
							}
						});
					}
					if(response.match('validationError')) {
						newComment.remove();
						eval(response);
					} else if(response != "spamprotectionfailed") {
						newComment.addClass('even');
						newComment.html(response);
						newComment.effects('highlight', {}, 1000);
						if(response.match('<b>Spam detected!!</b>')) {
							newComment.addClass('spam');
						}
						$(this).resetForm();
					} else {
						error.text('You got the spam question wrong');
						error.show();
						newComment.remove();
						$('#PageComments_holder #Math input').focus();
					}
				});
			} else {
				// We're missing things, alert it here
				
				error.show();
			}
			
			e.preventDefault();
		});
		
		/**
		 * Clicking one of the metalinks performs the operation via ajax
		 * this inclues the spam and approve links
		 */
		$(".action-links a").live('click', function(e) {
			var link = $(this);
			var hide = $(this).parents("li.comments");
			
			$.ajax({
				url: $(this).attr('href') + '?ajax=1',
				cache: false,
				success: function(html){
					if(link.hasClass('ham')) {
						// comment has been marked as not spam
						comment.html(html);
						comment.removeClass('spam');
						comment.effect("highlight", {}, 1000);
					}
					else if(link.hasClass('approve')) {
						// comment has been approved
						comment.html(html);
						comment.removeClass('unmoderated');
						comment.effect("highlight", {}, 1000);
					}
					else if(link.hasClass('delete')) {
						hide.fadeOut(1000, function() {
							var comments = hide.parents("ul");
							hide.remove();
									
							if(comments.children().length == 0) {
								comments.html("<p id=\"no-comments-yet\">No one has commented on this page yet.</p>");
							}
						});
					}
					else if(link.hasClass('spam')) {
						if(html) {
							hide.html(html);
							hide.effect("highlight", {}, 1000);
						} else {
							hide.fadeOut(1000, function() {				// Fade out the comment
								var comments = hide.parent();			// Grab the comments holder
								hide.remove();							// remove the comment
							
								if(comments.children().length == 0) {
									comments.html("<p id=\"no-comments-yet\">No one has commented on this page yet.</p>");
								}
							});
						}
					}
				},
				failure: function(html) {
					alert(html)
				}
			});
			
			e.preventDefault();
		})
	});
})(jQuery);
