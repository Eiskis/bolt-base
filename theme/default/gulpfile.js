
// Gulp + autoloaded plugins
var args = require('yargs').argv;
var del = require('del');
var gulp = require('gulp');

// Mostly autoloaded plugins
var plugins = require('gulp-load-plugins')();
plugins.bowerFiles = require('main-bower-files');



// Custom configs
var conf = {
	debug: false,
	browserlist: 'last 2 version, > 1%, Android, BlackBerry, iOS 7',
	bowerComponentsPath: 'bower_components',
	destination: 'public/',
	source: {
		css: [
			'css/**/*.css'
		],
		js: [
			'js/**/*.js'
		]
	}
};

// Command line arguments
if (args.debug) {
	conf.debug = true;
}



//
// TASKS
//

// Clean up CSS
gulp.task('clear-css', function (cb) {
	del([
		conf.destination + '**/*.css'
	], cb);
});

// Bower components
gulp.task('css-libraries', function() {
	return gulp.src(plugins.bowerFiles(['**/*.css']), { base: conf.bowerComponentsPath })
		.pipe(plugins.plumber())
		.pipe(plugins.concat('lib.css'))
		.pipe(plugins.if(!conf.debug, plugins.minifyCss()))
		// .pipe(plugins.rename({suffix: '.min'}))
		.pipe(gulp.dest(conf.destination));
});

// Compile, autoprefix and, minify CSS
gulp.task('css', ['clear-css', 'css-libraries'], function () {
	return gulp.src(conf.source.css)
		.pipe(plugins.plumber())
		.pipe(plugins.concat('all.css'))
		.pipe(plugins.autoprefixer(conf.browserlist))
		.pipe(plugins.if(!conf.debug, plugins.minifyCss()))
		// .pipe(plugins.rename({suffix: '.min'}))
		.pipe(gulp.dest(conf.destination));
});

// Clean up JS
gulp.task('clear-js', function (cb) {
	del([
		conf.destination + '**/*.js'
	], cb);
});

// Bower components
gulp.task('js-libraries', function() {
	return gulp.src(plugins.bowerFiles(['**/*.js']), { base: conf.bowerComponentsPath })
		.pipe(plugins.plumber())
		.pipe(plugins.concat('all.js', {newLine: ';'}))
		.pipe(plugins.if(!conf.debug, plugins.uglify()))
		// .pipe(plugins.rename({suffix: '.min'}))
		.pipe(gulp.dest(conf.destination));
});

// Compile, uglify JS
gulp.task('js', ['clear-js', 'js-libraries'], function () {
	return gulp.src(conf.source.js)
		.pipe(plugins.plumber())
		.pipe(plugins.concat('lib.js', {newLine: ';'}))
		.pipe(plugins.if(!conf.debug, plugins.uglify()))
		// .pipe(plugins.rename({suffix: '.min'}))
		.pipe(gulp.dest(conf.destination));
});

// Watch for changes, recompile when needed
gulp.task('watch', function () {
	for (var ext in conf.source) {
		(function () {
			var e = ext;
			plugins.watch(conf.source[e], plugins.batch(function () {
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
