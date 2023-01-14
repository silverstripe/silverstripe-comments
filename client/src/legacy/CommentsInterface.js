/* global ss, jQuery */
/**
 * @package comments
 */
(function ($) {
    // The above closure encapsulates the $ variable away from the global scope
    // and the one below is the `$(document).ready(...)` shorthand.
  $(() => {
    // Override the default URL validator in order to extend it to allow protocol-less URLs
    $.validator.methods.url = function (value, element) {
      // This line is copied directly from the jQuery.validation source (version 1.19.5)
      // the only change is a single question mark added here -------v
      // eslint-disable-next-line max-len
      return this.optional(element) || /^(?:(?:(?:https?|ftp):)?\/\/)?(?:(?:[^\]\[?\/<~#`!@$^&*()+=}|:";',>{ ]|%[0-9A-Fa-f]{2})+(?::(?:[^\]\[?\/<~#`!@$^&*()+=}|:";',>{ ]|%[0-9A-Fa-f]{2})*)?@)?(?:(?!(?:10|127)(?:\.\d{1,3}){3})(?!(?:169\.254|192\.168)(?:\.\d{1,3}){2})(?!172\.(?:1[6-9]|2\d|3[0-1])(?:\.\d{1,3}){2})(?:[1-9]\d?|1\d\d|2[01]\d|22[0-3])(?:\.(?:1?\d{1,2}|2[0-4]\d|25[0-5])){2}(?:\.(?:[1-9]\d?|1\d\d|2[0-4]\d|25[0-4]))|(?:(?:[a-z0-9\u00a1-\uffff][a-z0-9\u00a1-\uffff_-]{0,62})?[a-z0-9\u00a1-\uffff]\.)+(?:[a-z\u00a1-\uffff]{2,}\.?))(?::\d{2,5})?(?:[/?#]\S*)?$/i.test(value);
    };

    /**
     * Enable form validation
     */
    $('.comments-holder-container form').each(function () {
      $(this).validate({
        // Ignore hidden elements in this form
        ignore: ':hidden',

        // Use default 'required' for error labels
        errorClass: 'required',

        // Use span instead of labels
        errorElement: 'span',

        // On error, scroll to the invalid element
        invalidHandler(form, validator) {
          $('html, body').animate({
            scrollTop: $(validator.errorList[0].element).offset().top - 30
          }, 200);
        },

        // Ensure any new error message has the correct class and placement
        errorPlacement(error, element) {
          error
            .addClass('message')
            .insertAfter(element);
        }
      });
    });

    /**
     * Hide comment reply forms by default (unless visiting via permalink)
     */
    $('.comment')
      .children('.info')
      .not(window.document.location.hash)
      .nextAll('.comment-replies-container')
      .children('.comment-reply-form-holder')
      .hide();

    /**
     * Toggle on/off reply form
     */
    $('.comments-holder').on('click', '.comment-reply-link', function (e) {
      const allForms = $('.comment-reply-form-holder');
      const formID = `#${$(this).attr('aria-controls')}`;
      const form = $(formID).closest('.comment-reply-form-holder');

      $(this).attr('aria-expanded', (i, attr) => (attr === 'true' ? 'false' : 'true'));

      // Prevent focus
      e.preventDefault();

      if (form.is(':visible')) {
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
    $('.comments-holder .comments-list').on('click', 'div.comment-moderation-options a', function (e) {
      e.stopPropagation();

      const link = $(this);
      if (link.hasClass('delete')) {
        const confirmationMsg = ss.i18n._t('CommentsInterface_singlecomment_ss.DELETE_CONFIRMATION');
        const confirmation = window.confirm(confirmationMsg);
        if (!confirmation) {
          e.preventDefault();
          return false;
        }
      }
      const comment = link.parents('.comment:first');

      $.ajax({
        url: $(this).attr('href'),
        cache: false,
        success(html) {
          if (link.hasClass('ham')) {
            // comment has been marked as not spam
            comment.html(html);
            comment.removeClass('spam');
          } else if (link.hasClass('approve')) {
            // comment has been approved
            comment.html(html);
            comment.removeClass('unmoderated');
          } else if (link.hasClass('delete')) {
            comment.fadeOut(1000, () => {
              comment.remove();

              if ($('.comments-holder .comments-list').children().length === 0) {
                $('.no-comments-yet').show();
              }
            });
          } else if (link.hasClass('spam')) {
            comment.html(html).addClass('spam');
          }
        },
        failure(html) {
          const errorMsg = ss.i18n._t('CommentsInterface_singlecomment_ss.AJAX_ERROR');
          alert(errorMsg);
        }
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
		 * Hide comment update forms by default
		 */
		$(".comment")
			.children(".comment-update-form-holder")
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
		 * Toggle on/off update form
		 */
		$('.comments-holder').on('click', '.comment-update-link', function(e) {
			var allForms = $('.comment-update-form-holder');
			var formID = '#' + $(this).attr('aria-controls');
			var form = $(formID).closest('.comment-update-form-holder');

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
