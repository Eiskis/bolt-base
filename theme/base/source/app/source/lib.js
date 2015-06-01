(function (root) {

	var Main = function (root) {
		var self = this;
		self.win = root;
		self.debug = false;

		self.bindings = {

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

			}

		};



		// Init routines
		self.routines = {

			jQuery: {
				test: function (win) {
					return win.jQuery ? true : false;
				},
				callback: function (win) {
					var doc = $(win.document);

					// Toggle triggers
					// doc.on('click', '[data-action="toggle"]', self.bindings.toggleLinks);

					// Scoped scroll
					// doc.on('DOMMouseScroll mousewheel', '[data-scroll="scoped"]', self.bindings.scrollScoping);

				}
			},

			ScrollScope: {
				test: function (win) {
					return win.ScrollScope ? true : false;
				},
				callback: function (win) {
					return $(win.document).scrollScope();
				}
			},

			naturalScroll: {

				callback: function (win) {
					var binding = function (event) {
						event.preventDefault();

						// Find target object
						var targetAttr = this.getAttribute('data-target');
						if (!targetAttr) {
							targetAttr = this.getAttribute('href');
						}

						if (targetAttr) {
							var target = $(targetAttr).first();

							// Scroll
							if (target.length) {
								naturalScroll.scrollTop(win.document.body, Math.ceil(target.offset().top));
							}

						}

					};

					// Scroll links
					$(win.document.body).on('click', '[data-action="scroll"]', binding);
				}

			},

			FastClick: {
				test: function (win) {
					return win.FastClick ? true : false;
				},
				callback: function (win) {
					return win.FastClick.attach(win.document.body);
				}
			}

		};



		self.trace = function () {
			if (self.debug) {
				return self.notice.apply(this, arguments);
			}
			return self;
		};

		self.notice = function () {
			if (console && console.log) {
				console.log.apply(console, arguments);
			}
			return self;
		};



		self.runRoutine = function (routine) {

			// Pick from stored routines
			if (self.routines[routine]) {
				routine = self.routines[routine];
			}

			if (routine.callback) {

				// Handle input
				var input = [self.win];
				if (routine.input && routine.input instanceof Array) {
					input = routine.input;
				}

				// Run validity test
				if (routine.test && !routine.test.apply(this, input)) {
					return false;
				}

				// Run initialization callback
				self.trace('Launching callback', routine.callback);
				return routine.callback.apply(this, input);
			}

			return null;
		};

		// All of the document
		self.open = function () {
			for (var key in self.routines) {
				self.trace('Starting routine ' + key, self.routines[key]);
				if (self.runRoutine(self.routines[key]) === false) {
					self.notice(key + ' initialization routine could not be run during startup');
				}
			}
		};

	};

	root.janitor = new Main(root);

})(window);
