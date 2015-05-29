
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
	destination: 'build/',
	cssUrlPrefix: '../public/',
	source: {
		css: [
			'source/**/*.css'
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
gulp.task('css-libraries', function() {
	return gulp.src(plugins.bowerFiles(['**/*.css']), { base: config.bowerComponentsPath })
		.pipe(plugins.plumber())
		.pipe(plugins.concat('lib.css'))
		.pipe(plugins.if(!config.debug, plugins.minifyCss()))
		// .pipe(plugins.rename({suffix: '.min'}))
		.pipe(gulp.dest(config.destination));
});

// Compile, autoprefix and, minify CSS
gulp.task('css', ['clear-css', 'css-libraries'], function () {
	var files = gulp.src(config.source.css);
	return files
		.pipe(plugins.plumber())
		.pipe(plugins.concat('all.css'))
		.pipe(plugins.cssUrlAdjuster({
			prepend: config.cssUrlPrefix
		}))
		.pipe(plugins.autoprefixer(config.browserlist))
		.pipe(plugins.if(!config.debug, plugins.minifyCss()))
		// .pipe(plugins.rename({suffix: '.min'}))
		.pipe(gulp.dest(config.destination));
});

// Clean up JS
gulp.task('clear-js', function (cb) {
	del([
		config.destination + '**/*.js'
	], cb);
});

// Bower components
gulp.task('js-libraries', function() {
	return gulp.src(plugins.bowerFiles(['**/*.js']), { base: config.bowerComponentsPath })
		.pipe(plugins.plumber())
		.pipe(plugins.concat('all.js', {newLine: ';'}))
		.pipe(plugins.if(!config.debug, plugins.uglify()))
		// .pipe(plugins.rename({suffix: '.min'}))
		.pipe(gulp.dest(config.destination));
});

// Compile, uglify JS
gulp.task('js', ['clear-js', 'js-libraries'], function () {
	var files = gulp.src(config.source.js);
	return files
		.pipe(plugins.plumber())
		.pipe(plugins.concat('lib.js', {newLine: ';'}))
		.pipe(plugins.if(!config.debug, plugins.uglify()))
		// .pipe(plugins.rename({suffix: '.min'}))
		.pipe(gulp.dest(config.destination));
});

// Watch for changes, recompile when needed
gulp.task('watch', function () {
	for (var ext in config.source) {
		(function () {
			var e = ext;
			plugins.watch(config.source[e], plugins.batch(function () {
				gulp.start(e);
			}));
		})()
	}
});



//
// SHORTHANDS
//

// Build all
gulp.task('build', function () {
	gulp.start('css', 'js');
});

// Clean all
gulp.task('clear', function () {
	gulp.start('clear-css', 'clear-js');
});

// Build all and start watching for changes
gulp.task('build-and-watch', ['build'], function () {
	gulp.start('watch');
});

// Default
gulp.task('default', [], function () {
	gulp.start('build-and-watch');
});
