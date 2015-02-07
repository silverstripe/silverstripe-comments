/*
	Example of interacting with events from the comments interface javascript, for oldest threaded comments
	at the top of the page (Lineage ASC).  Override in your own theme if different behaviour required.
*/
(function($){

	$( document ).ready(function() {
		function regenerateCaptcha() {
			Recaptcha.create("YOUR KEY",   // update with your api key
			    "Captcha",
			    {
			      theme: "red",
			      callback: Recaptcha.focus_response_field
			    }
			  );
		}

		$(document).on('onBeforeLoadNewPageOfComments', function(e) {
			// do something such as add a spinner
		});


		$(document).on('onAfterLoadNewPageOfComments', function(e) {
			// do something like remove a spinner
		});

		$(document).on('replyToCommentButtonClicked', function(e) {
			// perhaps indicate the button has been pressed somehow
		});

		$(document).on('cancelReplyToCommentButtonClicked', function(e) {
			//alert('after new page of comments is loaded');
		});

		$(document).on('commentFormInitliased', function(e) {
			/*
			If using Google's recaptcha you can regenerate it here:

			$(document).ready(function() {
				regenerateCaptcha();
			});
			*/
		});

		$(document).on('onAfterSuccessfulNewComment', function(e) {
			var newcomment = null;
			if (e.parentcommentid == 0) {
				//console.log('No parent ID');
			}
			else {
				var commentdomid = '#comment-'+e.parentcommentid;
				var selector = "[data-comment-id='"+e.parentcommentid+"']";
				var comment = $(selector);
				comment.parent().find('li.cancelReplyButton').find('button').click();
				newcomment = comment;
				//comment.effect("highlight");
			}

			// insert a message into the top of the comment to indicate that the comment has been posted
			if (e.requiresmoderation) {
				var html = '<p class="commentsuccess">'+e.message+'</p>';
				var msg = $(html);
				// new root level comment
				if (e.parentcommentid == 0) {
					msg.insertAfter($('#Form_CommentsForm'));
					setTimeout(function() {msg.fadeOut(3000);},2000);
				} else {
					// a reply
					newcomment.prepend(msg);
					$('body,html').animate({
						scrollTop: newcomment.offset().top - 30
					}, 200);
					setTimeout(function() {msg.fadeOut(3000);},2000);
				}
				
			} else {
				var url = '/CommentingController/viewcomment/'+e.commentid;

				$.ajax({
					url: url,
					cache: false,
					success: function(html){
						var cssclass = 'depth'+e.depth+' comment notspam '
						// include the successful posting message
						html = '<p class="commentsuccess">'+e.message+'</p>'+html;
						var nchtml = '<li class="'+cssclass+'">'+html+'</li>';

						// insert replying comment after parent
						// FIXME - order needs to be taken account of here, e.g. Lineage DESC, Lineage ASC
						if (e.parentcommentid) {
							$(nchtml).insertAfter(newcomment.parent());
						} else {
							$('ul.comments-list').append(nchtml);
							newcomment = $('ul.comments-list').find('li.comment').last(); // FIXME - this depends on direction of comment ordering
						}				

						$('html,body').animate({
							scrollTop: newcomment.offset().top - 30
						}, 100);
					},
					failure: function(html) {
						alert(html);
					}
				});

			}


			var submitbutton = $('#Form_CommentsForm_action_doPostComment');
			submitbutton.val('Post'); // FIXME i18n
			submitbutton.removeAttr('disabled');
		});

		$(document).on('onBeforeValidateNewCommentForm', function(e) {
			// event thrown before form is validated
		});

		$(document).on('onAfterModerationClick', function(e) {
			e.commentNode.find('.action-links').find('a').addClass('button radius alert small');
		});

		$(document).on('onBeforeSubmitNewCommentForm', function(e) {
			// before form submission, perhaps add a spinner
			var submitbutton = $('#Form_CommentsForm_action_doPostComment');
			submitbutton.val('Posting'); // FIXME i18n
			submitbutton.attr('disabled',1);
		});

		$(document).on('onAfterServerValidationFailedNewCommentForm', function(e) {
			var submitbutton = $('#Form_CommentsForm_action_doPostComment');
			submitbutton.val('Post'); // FIXME i18n
			submitbutton.removeAttr('disabled');
		});

	});

})(jQuery);