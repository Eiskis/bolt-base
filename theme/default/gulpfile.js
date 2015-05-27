
// Gulp + autoloaded plugins
var args = require('yargs').argv;
var gulp = require('gulp');
var g = require('gulp-load-plugins')();



// Custom configs
var conf = {
	debug: false,
	browserlist: 'last 2 version, > 1%, Android, BlackBerry, iOS 7',
	paths: {
		css: 'css/',
		js: 'js/'
	},
	files: {
		css: [
			'**/*.css'
		],
		js: [
			'**/*.js'
		]
	}
};

// Prefix file paths with the theme path
(function (conf) {
	for (var ext in conf.files) {
		if (conf.paths[ext] instanceof Array) {
			for (var i = 0; i < conf.files[ext].length; i++) {
				conf.files[ext][i] = conf.paths[ext] + conf.files[ext][i];
			}
		}
	}
})(conf);

// Command line arguments
if (args.debug) {
	conf.debug = true;
}



// Compile, autoprefix and, minify
gulp.task('css', function() {
	return gulp.src(conf.files.css)
		.pipe(g.plumber())
		.pipe(g.concat('all.css'))
		.pipe(g.autoprefixer(conf.browserlist))
		.pipe(g.if(conf.debug, g.minifyCss()))
		.pipe(g.rename({suffix: '.min'}))
		.pipe(gulp.dest(conf.paths.css));
});

// Compile, uglify JS
gulp.task('js', function() {
	return gulp.src(conf.files.js)
		.pipe(g.plumber())
		.pipe(g.concat('all.js', {newLine: ';'}))
		.pipe(g.if(conf.debug, g.uglify()))
		.pipe(g.rename({suffix: '.min'}))
		.pipe(gulp.dest(conf.paths.js));
});



// Clean up
// gulp.task('unbuild', function(cb) {
// 	del([
// 		conf.paths.css + 'all.min.css',
// 		conf.paths.js + 'all.min.js'
// 	],cb)
// });



// Build all
gulp.task('build', [], function() {
	gulp.start('css', 'js');
});

// Watch for changes, recompile when needed
gulp.task('watch', function () {
	for (var ext in conf.files) {
		(function () {
			var e = ext;
			g.watch(conf.files[e], g.batch(function () {
				gulp.start(e);
			}));
		})()
	}
});



// Default
gulp.task('default', [], function() {
	gulp.start('build', 'watch');
});
