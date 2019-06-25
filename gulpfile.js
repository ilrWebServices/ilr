const gulp = require("gulp");
const sass = require("gulp-sass");
const livereload = require("gulp-livereload");

var sass_config = {
  includePaths: [
    'node_modules/',
  ],
  outputStyle: "compressed"
};

// CSS task
function css() {
  return gulp
    .src('web/themes/custom/union_marketing/scss/style.scss', { sourcemaps: true })
    .pipe(sass(sass_config)
      .on('error', sass.logError))
    .pipe(gulp.dest('web/themes/custom/union_marketing/css', { sourcemaps: '.' }));
}

function livereloadStartServer(done) {
  livereload.listen({ 'port': 35779 });
  done();
}

function watchFiles(done) {
  gulp.watch('web/themes/custom/union_marketing/scss/**/*.scss', css);

  var lr_watcher = gulp.watch([
    'web/libraries/union/source/**/*.css',
    'web/themes/custom/union_marketing/css/style.css'
  ]);

  lr_watcher.on('change', livereload.changed);

  done();
}

const watch = gulp.parallel(css, watchFiles, livereloadStartServer);

exports.sass = css
exports.default = watch
