/**
 * Comment Threading
 * @package comments
 */
(function($) {
	$(document).ready(function () {
		
		//Replying comments
		$('body').on('click','.replycomment', function(){
			var $this = $(this);
			var $rID = $this.attr('data-id');
			var url = $this.attr('href') + '?ReturnURL='  + location.pathname;
			$.get(url, function(data){
				var holder = $('.replycommentformholder[data-id=' + $rID + ']');
				holder.html($(data));
				$this.hide();
			});
			return false;
		});
		
	});
})(jQuery);
