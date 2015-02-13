/**
 * @package comments
 */
(function($) {
	$(document).ready(function () {

		var container = $('.comments-holder-container'),
			commentsHolder = $('.comments-holder'),
			commentsList = $('.comments-list', commentsHolder),
			pagination = $('.comments-pagination'),
			noCommentsYet = $('.no-comments-yet', commentsHolder),
			form = $('form', container),
			previewEl = form.find('#PreviewComment');

		if (container.length > 0) {
			/**
			 * Init
			 */
			previewEl.hide();
			$(':submit[name=action_doPreviewComment]').show();

			/**
			 * Validate
			 */
			form.validate({
			invalidHandler : function(form, validator){
					$('html, body').animate({
				scrollTop: $(validator.errorList[0].element).offset().top - 30
				}, 200);
				},
				showErrors: function(errorMap, errorList) {
				this.defaultShowErrors();
				// hack to add the extra classes we need to the validation message elements
				form.find('span.error').addClass('message required');
			},

				errorElement: "span",
				errorClass: "error",
				ignore: '.hidden',
				rules: {
					Name : {
						required : true
					},
					Email : {
						required : true,
						email : true
					},
					Comment: {
						required : true
					},
					URL: {
						url : true
					}
				},
				messages: {
					Name : {
						required : form.find('[name="Name"]').data('message-required')
					},
					Email : {
						required : form.find('[name="Email"]').data('message-required'),
						email : form.find('[name="Email"]').data('message-email')
					},
					Comment: {
						required : form.find('[name="Comment"]').data('message-required')
					},
					URL: {
						url : form.find('[name="Comment"]').data('message-url')
					}
				}
			});

		
			function applyAjaxButtonClick() {
				form = $('form', container);
				form.submit( function (e) {
					var formbutton = $('#Form_CommentsForm_action_doPostComment');
					formbutton.prop('disabled', true);

					$.event.trigger({
						type: "onBeforeValidateNewCommentForm",
					});

					// trigger validation
					if(!form.validate().valid()) {
						formbutton.prop('disabled', false);
						return false;
					}

					formbutton.html("Posting...");

					$.event.trigger({
						type: "onBeforeSubmitNewCommentForm",
					});

					$.ajax({
			            type: form.attr('method'),
			            url: form.attr('action'),
			            data: form.serialize(),
			           // accepts:"application/json",
			            dataType: 'json',
			            success: function (dataj) {

			            	if (typeof(dataj.success) === 'undefined') {
			            		$.event.trigger({
									type: "onAfterServerValidationFailedNewCommentForm",
									commentid: dataj.commentid,
									parentcommentid: dataj.parentcommentid,
									success: dataj.success,
									message: dataj.message,
									formhtml: dataj.html,
									requiresmoderation: dataj.requiresmoderation,
									depth: dataj.depth
								});

			            		var message = dataj[0].message;
			            		var fieldName = dataj[0].fieldName;
			            		var errormsg='<span for="'+fieldName+'" class="error message">'+message+'</span>';
			            		var field=$('#'+fieldName);

			            		$(errormsg).insertAfter(field);

			            		var formbutton = $('#Form_CommentsForm_action_doPostComment');
								formbutton.html("Post");
								formbutton.prop('disabled', false);
			            	} else {
			            		
			            		var data = dataj;
				            	var wrapper = $('#commentFormInnerWrapper');
				            	wrapper.html(data.html);

				            	var msg = $('#commentFormWrapper').find('p.message').first();
				            	var commentbox = $('#comment-'+data.parentcommentid);
				            	msg.insertAfter(commentbox);//.parent().parent());

				            	var formbutton = $('#Form_CommentsForm_action_doPostComment');
								formbutton.html("Post");
								formbutton.removeAttr('disabled');

								// click the canel button to reset the comment form
								commentbox.find('li.cancelReplyButton').find('button').click();

								// remove submitted comment
								$('#Form_CommentsForm_Comment').val('');

								applyAjaxButtonClick();

								$.event.trigger({
									type: "onAfterSuccessfulNewComment",
									commentid: data.commentid,
									parentcommentid: data.parentcommentid,
									success: data.success,
									message: data.message,
									formhtml: data.html,
									requiresmoderation: data.requiresmoderation,
									depth: data.depth
								});

			            	}
			            }
			        });

			        e.preventDefault();
				});
			}

			applyAjaxButtonClick();
			$.event.trigger({
				type: "commentFormInitliased",
			});

			/**
			 * Preview comment by fetching it from the server via ajax.
			 */
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

			/**
			 * Hide outdated preview on form changes
			 */
			$(':input', form).on('change keydown', function() {
				previewEl.removeClass('loading').hide();
			});
			
			/**
			 * Clicking one of the metalinks performs the operation via ajax
			 * this inclues the spam and approve links
			 */
			commentsList.on('click', '.action-links a', function(e) {
				var link = $(this);
				var comment = link.parents('.comment:first');

				$.event.trigger({
					type: "onBeforeModerationClick",
				});
				
				$.ajax({
					url: $(this).attr('href'),
					cache: false,
					success: function(html){
						if(link.hasClass('ham')) {
							// comment has been marked as not spam
							comment.html(html);
							comment.removeClass('spam').hide().fadeIn();
						}
						else if(link.hasClass('approve')) {
							// comment has been approved
							comment.html(html);
							comment.removeClass('unmoderated').hide().fadeIn();
						}
						else if(link.hasClass('delete')) {
							comment.fadeOut(1000, function() {
								comment.remove();
										
								if(commentsList.children().length == 0) {
									noCommentsYet.show();
								}
							});
						}
						else if(link.hasClass('spam')) {
							comment.html(html).addClass('spam').hide().fadeIn();
						}

						$.event.trigger({
							type: "onAfterModerationClick",
							commentNode: comment
						});
					},
					failure: function(html) {
						alert(html)
					}
				});
				
				e.preventDefault();
			});

			/*
			Deal with reply to buttons
			*/
			$(document).on("click",".replyButton button",function(e){
				// get the root node of the comment
				var commentbox = $(this).closest('.comment');

				commentbox.parent().append($('#CommentsFormContainer'));//.parent().parent());
				$(this).parent().addClass('hidden'); // hide reply button

				$(this).parent().parent().find('.cancelReplyButton').removeClass('hidden'); // show cancel reply button
				$('.whenReplying').removeClass('hidden');
				$('.newComment').addClass('hidden');
				var parentCommentID = $(this).attr('data-comment-to-reply-to-id');
				$('#Form_CommentsForm_ParentCommentID').val(parentCommentID);
				$.event.trigger({
					type: "replyToCommentButtonClicked",
					commentid: commentbox.attr('data-comment-id')
				});
			});


			$(document).on("click",".cancelReplyButton button",function(e){
				$($('#CommentsFormContainer')).insertAfter($('#postYourCommentHeader'));
				$(this).parent().parent().find('.replyButton').removeClass('hidden');
				$(this).parent().addClass('hidden');

				$('.newComment').removeClass('hidden');
				$('.whenReplying').addClass('hidden');
				var commentid = $('#Form_CommentsForm_ParentCommentID').val();
				$('#Form_CommentsForm_ParentCommentID').val(0);
				e.preventDefault();
				$.event.trigger({
					type: "cancelReplyToCommentButtonClicked",
					commentid: commentid
				});
				return false;
			});



			/**
			 * Ajax pagination
			 */
			pagination.find('a').on('click', function(e){
				commentsList.addClass('loading');
				$.event.trigger({
					type: "onBeforeLoadNewPageOfComments",
					page: $(this).html()
				});
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
						$.event.trigger({
							type: "onAfterLoadNewPageOfComments",
							page: $(e.target).html()
						});
					},
					failure: function(html) {
						alert('Error loading comments');
					}
				});
				return false;
			});
		}

		
	});
})(jQuery);
