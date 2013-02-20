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
			form = $('form', container);


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


		/**
		 * Clicking one of the metalinks performs the operation via ajax
		 * this inclues the spam and approve links
		 */
		form.submit(function (e) {

			// trigger validation
			if(!form.validate().valid()){
				return false;
			}
		
			// submit the form
			$(this).ajaxSubmit(function(response) {
				noCommentsYet.hide();	

				if(!commentsList.length){
					commentsHolder.append("<ul class='comments-list'></ul>");
					commentsList = $('.comments-list', commentsHolder);
				}

				var newComment = $('<li />')
					.addClass('even first')
					.html(response)
					.hide();

				if(response.match('<b>Spam detected!!</b>')) {
					newComment.addClass('spam');
				}

				commentsList.prepend(newComment.fadeIn());
			});

			$(this).resetForm();
			
			return false;
		});

		
		/**
		 * Clicking one of the metalinks performs the operation via ajax
		 * this inclues the spam and approve links
		 */
		$(".action-links a", commentsList).live('click', function(e) {
			var link = $(this);
			var comment = link.parents('.comment:first');
			
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
				},
				failure: function(html) {
					alert(html)
				}
			});
			
			e.preventDefault();
		});


		/**
		 * Ajax pagination
		 */
		pagination.find('a').live('click', function(){
			commentsList.addClass('loading');
			$.ajax({
				url: $(this).attr('href'),
				cache: false,
				success: function(html){
					html = $(html);
					commentsList.hide().html(html.find('.comments-list:first').html()).fadeIn();
					pagination.hide().html(html.find('.comments-pagination:first').html()).fadeIn();
					commentsList.removeClass('loading');
				},
				failure: function(html) {
					alert('Error loading comments');
				}
			});
			return false;
		});
	});
})(jQuery);
