(function (root) {

	var Lib = function () {
		var self = this;

		self.bindings = {

			// Mark scrolled document
			documentScrolling: function (event) {
				var scroll = $(document).scrollTop();
				var body = $(document.body);
				if (scroll > 0) {
					body.addClass('scrolled');
				} else {
					body.removeClass('scrolled');
				}
			},

			popupLinks: function (event) {
				event.preventDefault();
				var link = $(this);
				var target = link.attr('href');
				var image = link.attr('data-target-image') ? true : false;

				// AJAX popup
				if (target) {
					$.magnificPopup.open({
						type: (image ? 'image' : 'ajax'),
						closeOnContentClick: image,
						fixedContentPos: true,
						tLoading: '',
						preloader: true,
						removalDelay: 800,
						closeBtnInside: false,
						items: {
							src: target
						}
					});
				}

			},

			scrollLinks: function (event) {
				event.preventDefault();

				// Find target object
				var targetAttr = this.getAttribute('data-target');
				if (!targetAttr) {
					targetAttr = this.getAttribute('href');
				}

				if (targetAttr) {
					var target = $(targetAttr).first();

					if (target.length) {

						// Offset fixed title bar
						var offset = 0;
						var titlebar = $('.layout-header.fixed');
						if (titlebar.length) {
							offset = titlebar.outerHeight();
						}

						// Scroll
						naturalScroll.scrollTop(document.body, Math.ceil(target.offset().top - offset));
					}

				}

			},

			toggleLinks: function (event) {
				event.preventDefault();
				var target;
				var targetAttr = this.getAttribute('data-target');
				var acceptableFilter = function (index) {
					var e = $(this);
					return e.hasClass('on') || e.hasClass('off');
				};

				// Target set explicitly (FIXME: not very powerful or reliable)
				if (targetAttr) {
					target = $(targetAttr).filter(acceptableFilter).first();
				} else {

					// First parent with on/off state defined
					target = $(this).parents().filter(acceptableFilter).first();

					// Self
					if (!target) {
						target = $(this);
					}

				}

				// Toggle classes
				if (target && !target.hasClass('animating')) {

					// Delay
					var delay = Math.abs(parseInt(target.attr('data-delay')));
					if (!delay) {
						delay = 0;
					}

					// Is open -> close
					if (target.hasClass('on')) {
						target.removeClass('on');
						target.addClass('off');

						// Additional delay for animation if requested (manually set to match CSS transition duration)
						if (delay) {
							target.addClass('animating');
							var timeout = setTimeout(function () {
								target.removeClass('animating');
							}, delay);
						}

					// Default to being closed -> open
					} else {
						target.removeClass('off');
						target.addClass('on');

						// Additional delay to handle display none etc.
						target.addClass('animating');
						var timeout = setTimeout(function () {
							target.removeClass('animating');
						}, 1);

					}

				}

			},

			// Prevents parent element from scrolling when a child element is scrolled to its boundaries
			scrollScoping: function (event) {
				var element = $(this);
				var scrollTop = this.scrollTop;
				var scrollHeight = this.scrollHeight;
				var height = element.outerHeight();
				var delta = event.originalEvent.wheelDelta;
				var up = delta > 0;

				// var scopedChildren = element.find('.scopedscroll');

				var prevent = function () {

					// THink of the children
					// FIXME: might be slow, fails on fast scrolls
					// if (scopedChildren.length) {
					// 	for (var i = 0; i < scopedChildren.length; i++) {
					// 		var child = $(scopedChildren[i]);

					// 		// Scrolling needs to continue
					// 		if (
					// 			up && child.scrollTop() > 0 ||
					// 			!up && child.scrollTop() < child[0].scrollHeight - child.outerHeight()
					// 		) {
					// 			return true;
					// 		}

					// 	}
					// }

					event.stopPropagation();
					event.preventDefault();
					event.returnValue = false;
					return false;
				}

				// Scrolling down, but this will take us past the bottom
				if (!up && -delta > scrollHeight - height - scrollTop) {
					element.scrollTop(scrollHeight);
					return prevent();

				// Scrolling up, but this will take us past the top
				} else if (up && delta > scrollTop) {
					element.scrollTop(0);
					return prevent();
				}

			}

		};

		self.inits = {

			// Wrap image into specific paragraph and popup link
			customContentImage: function (image) {
				var link = $('<a></a>').attr('href', image.attr('src')).attr('data-action', 'popup').attr('data-target-image', true);
				image.parent().addClass('layout-customcontent-image').wrapInner(link);
			},

			formAutoInteraction: function (form, responseElement) {

				// Focus
				form.on('click', '[data-action="focus"]', function (event) {
					event.preventDefault();
					var input = $(this);
					input.parents('form').first().find(input.attr('data-target')).focus();
				});

				// Auto submit form when its marked input elements change
				form.on('input change', '[data-action="submit-on-change"]', function (event) {
					$(this).submit();
				});

				// Response element showing/hiding
				form.on('input change focusin', '[data-action="submit-on-change"]', function (event) {
					var input = $(this);
					if ($.trim(input.val()).length) {
						responseElement.removeClass('hidden');
					}
				});
				form.on('focusout', '[data-action="submit-on-change"]', function (event) {
					var input = $(this);
					setTimeout(function () {
						if (!input.is(':focus')) {
							responseElement.addClass('hidden');
						}
					}, 200);
				});

			},

			// Submit handler
			formSubmit: function (form, responseElement) {
				form.on('submit', function (event) {
					event.preventDefault();
					var data = {};
					var target = form.attr('data-target');
					var inputs = form.find('textarea, input:not([type="submit"])');

					// Method
					var method = 'post';
					if (form.attr('method') && form.attr('method').toLowerCase() === 'get') {
						method = 'get';
					}

					// Get form data
					inputs.each(function (i, input) {
						input = $(input);
						var name = input.attr('name');
						if (name) {
							data[$.trim(name)] = $.trim(input.val());
						}
					});

					// Done
					$[method](target, data).done(function (d) {
						if (responseElement) {
							responseElement.html(d);
						}

					// Error callback
					// }).fail(function () {
					});

				});
			}

		};



		// All of the document
		self.initDocument = function (container) {

			// Libs
			if (window.FastClick) {
				window.FastClick.attach(container);
			}

			if (window.hljs) {
				window.hljs.initHighlightingOnLoad();
			}

			// Mark scrolled document reliably
			$(document).on('scroll touchmove', self.bindings.documentScrolling);
			$(window).on('resize', self.bindings.documentScrolling);
			self.bindings.documentScrolling();

			// Popup triggers
			$(container).on('click', '[data-action="popup"]', self.bindings.popupLinks);

			// Scroll links
			$(container).on('click', '[data-action="scroll"]', self.bindings.scrollLinks);

			// Toggle triggers
			$(container).on('click', '[data-action="toggle"]', self.bindings.toggleLinks);

			// Scoped scroll
			$(container).on('DOMMouseScroll mousewheel', '.scopedscroll', self.bindings.scrollScoping);

		};

		// Individual views (like AJAX loaded content after page load)
		self.initView = function (container) {

			// Mark top-level menu items selected
			// FIXME: shouldn't be done in JS
			$('.menu', container).each(function (i, menu) {
				menu = $(menu);
				var selectedListItems = menu.find('li.selected');
				selectedListItems.each(function (j, li) {
					li = $(li);
					li.parents('li:not(.selected)').addClass('selected');
				});
			});

			// Initialize AJAX forms
			$('form[data-target]', container).each(function (i, form) {
				form = $(form);
				var responseElement = form.next('.form-response');

				// Response element given, launch "auto" behavior (used in search)
				if (responseElement.length) {
					self.inits.formAutoInteraction(form, responseElement);
					self.inits.formSubmit(form, responseElement);

				// Submit
				} else {
					self.inits.formSubmit(form);
				}

			});

			// Wrap custom content images
			$('.layout-customcontent p img', container).each(function (i, image) {
				self.inits.customContentImage($(image));
			});

			return container;
		};

	};

	root.lib = new Lib();

})(window);
