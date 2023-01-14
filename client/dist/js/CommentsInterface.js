/******/ (function(modules) { // webpackBootstrap
/******/ 	// The module cache
/******/ 	var installedModules = {};
/******/
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/
/******/ 		// Check if module is in cache
/******/ 		if(installedModules[moduleId]) {
/******/ 			return installedModules[moduleId].exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = installedModules[moduleId] = {
/******/ 			i: moduleId,
/******/ 			l: false,
/******/ 			exports: {}
/******/ 		};
/******/
/******/ 		// Execute the module function
/******/ 		modules[moduleId].call(module.exports, module, module.exports, __webpack_require__);
/******/
/******/ 		// Flag the module as loaded
/******/ 		module.l = true;
/******/
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/
/******/
/******/ 	// expose the modules object (__webpack_modules__)
/******/ 	__webpack_require__.m = modules;
/******/
/******/ 	// expose the module cache
/******/ 	__webpack_require__.c = installedModules;
/******/
/******/ 	// identity function for calling harmony imports with the correct context
/******/ 	__webpack_require__.i = function(value) { return value; };
/******/
/******/ 	// define getter function for harmony exports
/******/ 	__webpack_require__.d = function(exports, name, getter) {
/******/ 		if(!__webpack_require__.o(exports, name)) {
/******/ 			Object.defineProperty(exports, name, {
/******/ 				configurable: false,
/******/ 				enumerable: true,
/******/ 				get: getter
/******/ 			});
/******/ 		}
/******/ 	};
/******/
/******/ 	// getDefaultExport function for compatibility with non-harmony modules
/******/ 	__webpack_require__.n = function(module) {
/******/ 		var getter = module && module.__esModule ?
/******/ 			function getDefault() { return module['default']; } :
/******/ 			function getModuleExports() { return module; };
/******/ 		__webpack_require__.d(getter, 'a', getter);
/******/ 		return getter;
/******/ 	};
/******/
/******/ 	// Object.prototype.hasOwnProperty.call
/******/ 	__webpack_require__.o = function(object, property) { return Object.prototype.hasOwnProperty.call(object, property); };
/******/
/******/ 	// __webpack_public_path__
/******/ 	__webpack_require__.p = "";
/******/
/******/ 	// Load entry module and return exports
/******/ 	return __webpack_require__(__webpack_require__.s = "./client/src/legacy/CommentsInterface.js");
/******/ })
/************************************************************************/
/******/ ({

/***/ "./client/src/legacy/CommentsInterface.js":
/***/ (function(module, exports, __webpack_require__) {

"use strict";
/* WEBPACK VAR INJECTION */(function(jQuery) {

(function ($) {
	$(function () {
		$.validator.methods.url = function (value, element) {
			return this.optional(element) || /^(?:(?:(?:https?|ftp):)?\/\/)?(?:\S+(?::\S*)?@)?(?:(?!(?:10|127)(?:\.\d{1,3}){3})(?!(?:169\.254|192\.168)(?:\.\d{1,3}){2})(?!172\.(?:1[6-9]|2\d|3[0-1])(?:\.\d{1,3}){2})(?:[1-9]\d?|1\d\d|2[01]\d|22[0-3])(?:\.(?:1?\d{1,2}|2[0-4]\d|25[0-5])){2}(?:\.(?:[1-9]\d?|1\d\d|2[0-4]\d|25[0-4]))|(?:(?:[a-z\u00a1-\uffff0-9]-*)*[a-z\u00a1-\uffff0-9]+)(?:\.(?:[a-z\u00a1-\uffff0-9]-*)*[a-z\u00a1-\uffff0-9]+)*(?:\.(?:[a-z\u00a1-\uffff]{2,})).?)(?::\d{2,5})?(?:[/?#]\S*)?$/i.test(value);
		};

		$('.comments-holder-container form').each(function () {
			$(this).validate({
				ignore: ':hidden',

				errorClass: "required",

				errorElement: "span",

				invalidHandler: function invalidHandler(form, validator) {
					$('html, body').animate({
						scrollTop: $(validator.errorList[0].element).offset().top - 30
					}, 200);
				},

				errorPlacement: function errorPlacement(error, element) {
					error.addClass('message').insertAfter(element);
				}
			});
		});

		$(".comment").children('.info').not(window.document.location.hash).nextAll(".comment-replies-container").children(".comment-reply-form-holder").hide();

		$(".comment").children(".comment-update-form-holder").hide();

		$('.comments-holder').on('click', '.comment-reply-link', function (e) {
			var allForms = $('.comment-reply-form-holder');
			var formID = '#' + $(this).attr('aria-controls');
			var form = $(formID).closest('.comment-reply-form-holder');

			$(this).attr('aria-expanded', function (i, attr) {
				return attr == 'true' ? 'false' : 'true';
			});

			e.preventDefault();

			if (form.is(':visible')) {
				allForms.slideUp();
			} else {
				allForms.not(form).slideUp();
				form.slideDown();
			}
		});

		$('.comments-holder').on('click', '.comment-update-link', function (e) {
			var allForms = $('.comment-update-form-holder');
			var formID = '#' + $(this).attr('aria-controls');
			var form = $(formID).closest('.comment-update-form-holder');

			$(this).attr('aria-expanded', function (i, attr) {
				return attr == 'true' ? 'false' : 'true';
			});

			e.preventDefault();

			if (form.is(':visible')) {
				allForms.slideUp();
			} else {
				allForms.not(form).slideUp();
				form.slideDown();
			}
		});

		$('.comments-holder .comments-list').on('click', 'div.comment-moderation-options a', function (e) {
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
				success: function success(html) {
					if (link.hasClass('ham')) {
						comment.html(html);
						comment.removeClass('spam');
					} else if (link.hasClass('approve')) {
						comment.html(html);
						comment.removeClass('unmoderated');
					} else if (link.hasClass('delete')) {
						comment.fadeOut(1000, function () {
							comment.remove();

							if ($('.comments-holder .comments-list').children().length === 0) {
								$('.no-comments-yet').show();
							}
						});
					} else if (link.hasClass('spam')) {
						comment.html(html).addClass('spam');
					}
				},
				failure: function failure(html) {
					var errorMsg = ss.i18n._t('CommentsInterface_singlecomment_ss.AJAX_ERROR');
					alert(errorMsg);
				}
			});

			e.preventDefault();
		});
	});
})(jQuery);
/* WEBPACK VAR INJECTION */}.call(exports, __webpack_require__(0)))

/***/ }),

/***/ 0:
/***/ (function(module, exports) {

module.exports = jQuery;

/***/ })

/******/ });
//# sourceMappingURL=CommentsInterface.js.map
