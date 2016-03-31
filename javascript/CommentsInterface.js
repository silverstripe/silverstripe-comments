/**
 * @package comments
 */
(function($) {
	$.entwine( "ss.comments", function($) {

		/**
		 * Enable form validation
		 */
		$('.comments-holder-container form').entwine({
			onmatch: function() {

				// @todo Reinstate preview-comment functionality

				/**
				 * Validate
				 */
				$(this).validate({

					/**
					 * Ignore hidden elements in this form
					 */
					ignore: ':hidden',

					/**
					 * Use default 'required' for error labels
					 */
					errorClass: "required",

					/**
					 * Use span instead of labels
					 */
					errorElement: "span",

					/**
					 * On error, scroll to the invalid element
					 */
					invalidHandler : function(form, validator){
						$('html, body').animate({
							scrollTop: $(validator.errorList[0].element).offset().top - 30
						}, 200);
					},

					/**
					 * Ensure any new error message has the correct class and placement
					 */
					errorPlacement: function(error, element) {
						error
							.addClass('message')
							.insertAfter(element);
					}
				});
				this._super();
			},
			onunmatch: function() {
				this._super();
			}
		});

		/**
		 * Toggle on/off reply form
		 */
		$( ".comment-reply-link" ).entwine({
			onclick: function( e ) {
                // Prevent focus
                e.preventDefault();

                var jqTarget = $(e.target);
                var commentID = jqTarget.attr('data-comment-id');
                container = $('#replyFormJS');
                var state = jqTarget.attr('data-state');

                // Show possibly hidden reply form, alter state so that it works
                // in context of relevant comment
                if (state != 'replying') {
                    cancelTheCancels();
                    toggleButtonText(jqTarget);

                    // hide the form if it's visible
                    if(container.is(':visible')) {
                        container.toggle();
                    }

                    var jqComment = $('#reply-form-container-' + commentID);
                    var form = container.find('form');
                    action = form.attr('action');
                    form.attr('action', '/CommentingController/reply/' + commentID);
                    var inputParemtCommentID = container.find("input[name='ParentCommentID']");
                    var inputComment = container.find("input[name='Comment']");
                    inputParemtCommentID.attr('value', commentID);
                    inputComment.attr('value', '');
                    container.detach().appendTo(jqComment);

                    var allForms = $( ".comment-reply-form-holder" ),
                        formID = $( this ).prop('href').replace(/^[^#]*#/, '#'),
                        form = $(formID).closest('.comment-reply-form-holder');

                    // Show the form
                    container.toggle();

                    $('html, body').animate({
                        scrollTop: container.offset().top - 30
                    }, 200);

                } else {
                    // Cancel reply, hide form, change button text
                    toggleButtonText(jqTarget);
                    container.slideUp();
                }
			}
		});

        /**
         * Revert buttons in a state of replying to that of cancelled, changing
         * text back to 'Reply to <person>'
         */
        function toggleButtonText(node) {
            var state = node.attr('data-state');
            if (state != 'replying') {
                node.html(ss.i18n._t('CommentsInterface_singlecomment_ss.CANCEL_REPLY'));
                node.attr('data-state', 'replying');
            } else {
                node.html(ss.i18n._t('CommentsInterface_singlecomment_ss.REPLY_TO'));
                node.attr('data-state', 'cancelled');
            }
        }

        function cancelTheCancels() {
            var toFix = $("a[data-state='replying']");
            toFix.each(function(index) {
                var jqTarget = $(this);
                toggleButtonText(jqTarget);
            });
        }

		/**
		 * Preview comment by fetching it from the server via ajax.
		 */
		/* @todo Migrate to work with nested comments
		$(':submit[name=action_doPreviewComment]', form).click(function(e) {
		   e.preventDefault();

		   if(!form.validate().valid()) {
			   return false;
		   }

		   previewEl.show().addClass('loading').find('.middleColumn').html(' ');

		   form.ajaxSubmit({
			   success: function(response) {
				   var responseEl = $(response);
				   if(responseEl.is('form')) {
					   // Validation failed, renders form instead of single comment
					   form.find(".data-fields").replaceWith(responseEl.find(".data-fields"));
				   } else {
					   // Default behaviour
					   previewEl.removeClass('loading').find('.middleColumn').html(responseEl);
				   }
			   },
			   data: {'action_doPreviewComment': 1}
		   });
		});
		*/

		/**
		 * Hide outdated preview on form changes
		 */
		/*
		$(':input', form).on('change keydown', function() {
		   previewEl.removeClass('loading').hide();
		});*/

		/**
		 * Clicking one of the metalinks performs the operation via ajax
		 * this inclues the spam and approve links
		 */

		$('.comments-holder .comments-list').on('click', 'div.comment-moderation-options a', function(e) {
			var link = $(this);
            if (link.hasClass('delete')) {
                var confirmationMsg = ss.i18n._t('CommentsInterface_singlecomment_ss.DELETE_CONFIRMATION');
                var confirmation = window.confirm(confirmationMsg);
                if (!confirmation) {
                    e.preventDefault();
                    return false;
                }
            }
			var comment = link.parents('.comment:first');

			$.ajax({
				url: $(this).attr('href'),
				cache: false,
				success: function(html){
					if(link.hasClass('ham')) {
						// comment has been marked as not spam
						comment.html(html);
						comment.removeClass('spam');
					}
					else if(link.hasClass('approve')) {
						// comment has been approved
						comment.html(html);
						comment.removeClass('unmoderated');
					}
					else if(link.hasClass('delete')) {
						comment.fadeOut(1000, function() {
							comment.remove();

							if(commentsList.children().length === 0) {
								noCommentsYet.show();
							}
						});
					}
					else if(link.hasClass('spam')) {
						comment.html(html).addClass('spam');
					}
				},
				failure: function(html) {
					var errorMsg = ss.i18n._t('CommentsInterface_singlecomment_ss.AJAX_ERROR');
                    alert(errorMsg);
				}
			});

			e.preventDefault();
		});

		/**
		 * Ajax pagination
		 */
		/* @todo Migrate to work with nested comments
		pagination.find('a').on('click', function(){
			commentsList.addClass('loading');
			$.ajax({
				url: $(this).attr('href'),
				cache: false,
				success: function(html){
					html = $(html);
					commentsList.hide().html(html.find('.comments-list:first').html()).fadeIn();
					pagination.hide().html(html.find('.comments-pagination:first').html()).fadeIn();
					commentsList.removeClass('loading');
					$('html, body').animate({
						scrollTop: commentsList.offset().top - 30
					}, 200);
				},
				failure: function(html) {
					alert('Error loading comments');
				}
			});
			return false;
		});*/
	});
})(jQuery);
