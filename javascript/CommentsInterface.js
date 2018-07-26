/**
 * @package comments
 */
(function($) {
	$(function() {
		/**
		 * Enable form validation
		 */
        $('.comments-holder-container form').each(function() {
            $(this).validate({

                // Ignore hidden elements in this form
                ignore: ':hidden',

                // Use default 'required' for error labels
                errorClass: "required",

                // Use span instead of labels
                errorElement: "span",

                // On error, scroll to the invalid element
                invalidHandler : function(form, validator){
                    $('html, body').animate({
                        scrollTop: $(validator.errorList[0].element).offset().top - 30
                    }, 200);
                },

                // Ensure any new error message has the correct class and placement
                errorPlacement: function(error, element) {
                    error
                        .addClass('message')
                        .insertAfter(element);
                }
            });
        });

		/**
		 * Hide comment reply forms by default (unless visiting via permalink)
		 */
		$(".comment")
			.children('.info')
			.not(window.document.location.hash)
			.nextAll(".comment-replies-container")
			.children(".comment-reply-form-holder")
			.hide();

		/**
		 * Toggle on/off reply form
		 */
		$('.comments-holder').on('click', '.comment-reply-link', function(e) {
			var allForms = $('.comment-reply-form-holder');
			var formID = '#' + $(this).attr('aria-controls');
			var form = $(formID).closest('.comment-reply-form-holder');

			$(this).attr('aria-expanded', function (i, attr) {
				return attr == 'true' ? 'false' : 'true'
			});

			// Prevent focus
			e.preventDefault();

			if(form.is(':visible')) {
				allForms.slideUp();
			} else {
				allForms.not(form).slideUp();
				form.slideDown();
			}
		});

		/**
		 * Clicking one of the metalinks performs the operation via ajax
		 * this inclues the spam and approve links
		 */
		$('.comments-holder .comments-list').on('click', 'div.comment-moderation-options a', function(e) {
		    e.stopPropagation();

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

							if($('.comments-holder .comments-list').children().length === 0) {
								$('.no-comments-yet').show();
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
	});
})(jQuery);
