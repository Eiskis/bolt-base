
// Gulp + autoloaded plugins
var args = require('yargs').argv;
var del = require('del');
var gulp = require('gulp');

// Mostly autoloaded plugins
var plugins = require('gulp-load-plugins')();
plugins.bowerFiles = require('main-bower-files');



// Custom configs
var config = {
	debug: false,
	browserlist: 'last 2 version, > 1%, Android, BlackBerry, iOS 7',
	bowerComponentsPath: 'bower_components',
	destination: 'public/build/',
	cssUrlPrefix: '../',
	source: {
		css: [
			'source/**/*.{css,less}'
		],
		js: [
			'source/**/*.js'
		]
	}
};

// Command line arguments
if (args.debug) {
	config.debug = true;
}



//
// TASKS
//

// Clean up CSS
gulp.task('clear-css', function (cb) {
	del([
		config.destination + '**/*.css'
	], cb);
});

// Bower components
gulp.task('css-vendor', function() {
	var files = gulp.src(plugins.bowerFiles(['**/*.css']), { base: config.bowerComponentsPath });
	return files
		.pipe(plugins.plumber())

		// Log files
		.pipe(plugins.if(config.debug, plugins.debug({
			title: 'File (vendor CSS): '
		})))

		.pipe(plugins.concat('vendor.css'))
		.pipe(plugins.if(!config.debug, plugins.minifyCss()))
		// .pipe(plugins.rename({suffix: '.min'}))
		.pipe(gulp.dest(config.destination));
});

// Compile, autoprefix and, minify CSS
gulp.task('css', function () {
	var files = gulp.src(config.source.css);
	return files
		.pipe(plugins.plumber())

		// Log files
		.pipe(plugins.if(config.debug, plugins.debug({
			title: 'File (CSS): '
		})))

		// We want a single file
		.pipe(plugins.concat('all.css'))

		// Parse all as a single LESS batch
		.pipe(plugins.less({
			paths: ['.'],
			compress: false
		}))

		// Destination changes, manipulate internal URLs to point to public/
		.pipe(plugins.cssUrlAdjuster({
			prepend: config.cssUrlPrefix
		}))

		// Autoprefix
		.pipe(plugins.autoprefixer(config.browserlist))

		// Minify if not in debug mode
		.pipe(plugins.if(!config.debug, plugins.minifyCss()))

		// Rename with suffix
		// .pipe(plugins.rename({suffix: '.min'}))

		// Done
		.pipe(gulp.dest(config.destination));
});

// Clean up JS
gulp.task('clear-js', function (cb) {
	del([
		config.destination + '**/*.js'
	], cb);
});

// Bower components
gulp.task('js-vendor', function() {
	var files = gulp.src(plugins.bowerFiles(['**/*.js']), { base: config.bowerComponentsPath });
	return files
		.pipe(plugins.plumber())

		// Log files
		.pipe(plugins.if(config.debug, plugins.debug({
			title: 'File (vendor JS): '
		})))

		.pipe(plugins.concat('vendor.js', {newLine: ';'}))
		.pipe(plugins.if(!config.debug, plugins.uglify()))
		// .pipe(plugins.rename({suffix: '.min'}))
		.pipe(gulp.dest(config.destination));
});

// Compile, uglify JS
gulp.task('js', function () {
	var files = gulp.src(config.source.js);
	return files
		.pipe(plugins.plumber())

		// Log files
		.pipe(plugins.if(config.debug, plugins.debug({
			title: 'File (JS): '
		})))

		.pipe(plugins.concat('all.js', {newLine: ';'}))
		.pipe(plugins.if(!config.debug, plugins.uglify()))
		// .pipe(plugins.rename({suffix: '.min'}))
		.pipe(gulp.dest(config.destination));
});



//
// SHORTHANDS
//

// Clean all
gulp.task('clear', [], function () {
	gulp.start('clear-css', 'clear-js');
});

// Build all
gulp.task('build', ['clear'], function () {
	gulp.start('css-vendor', 'css', 'js-vendor', 'js');
});

// Watch for changes, recompile when needed
gulp.task('watch', ['build'], function () {
	for (var ext in config.source) {
		(function () {
			var e = ext;

			// plugins.watch(config.source[e], plugins.batch(function () {
			// 	gulp.start(e);
			// }));

			plugins.watch(config.source[e], function () {
				gulp.start(e);
			});

		})()
	}
});

// Default
gulp.task('default', [], function () {
	gulp.start('watch');
});
