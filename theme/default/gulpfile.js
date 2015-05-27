
// Gulp + autoloaded plugins
var args = require('yargs').argv;
var del = require('del');
var gulp = require('gulp');
var g = require('gulp-load-plugins')();



// Custom configs
var conf = {
	debug: false,
	browserlist: 'last 2 version, > 1%, Android, BlackBerry, iOS 7',
	destination: {
		css: 'css/',
		js: 'js/'
	},
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
gulp.task('clean-css', function (cb) {
	del(conf.destination.css + 'all.min.css', cb)
});

// Compile, autoprefix and, minify CSS
gulp.task('css', ['clean-css'], function () {
	return gulp.src(conf.source.css)
		.pipe(g.plumber())
		.pipe(g.concat('all.css'))
		.pipe(g.autoprefixer(conf.browserlist))
		.pipe(g.if(conf.debug, g.minifyCss()))
		.pipe(g.rename({suffix: '.min'}))
		.pipe(gulp.dest(conf.destination.css));
});

// Clean up JS
gulp.task('clean-js', function (cb) {
	del(conf.destination.js + 'all.min.js', cb)
});

// Compile, uglify JS
gulp.task('js', ['clean-js'], function () {
	return gulp.src(conf.source.js)
		.pipe(g.plumber())
		.pipe(g.concat('all.js', {newLine: ';'}))
		.pipe(g.if(conf.debug, g.uglify()))
		.pipe(g.rename({suffix: '.min'}))
		.pipe(gulp.dest(conf.destination.js));
});

// Watch for changes, recompile when needed
gulp.task('watch', function () {
	for (var ext in conf.source) {
		(function () {
			var e = ext;
			g.watch(conf.source[e], g.batch(function () {
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

// Build all and start watching for changes
gulp.task('build-and-watch', ['build'], function () {
	gulp.start('watch');
});

// Default
gulp.task('default', [], function () {
	gulp.start('build-and-watch');
});
