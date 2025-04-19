const gulp = require('gulp');
const uglify = require('gulp-uglify');
const cleanCSS = require('gulp-clean-css');
const rename = require('gulp-rename'); // Importer gulp-rename

// Tâche pour supprimer les fichiers *.min.*
gulp.task('clean', async () => {
    const { deleteAsync } = await import('del'); // Import dynamique
    return deleteAsync(['src/assets/**/*.min.*']);
});

// Minification JavaScript
gulp.task('minify-js', () => {
    return gulp.src('src/assets/js/*.js') // Chemin des fichiers JS d'origine
        .pipe(uglify()) // Compresser les fichiers JS
        .pipe(rename({ suffix: '.min' })) // Ajouter ".min" au nom du fichier
        .pipe(gulp.dest('src/assets/js')); // Répertoire de sortie
});

// Minification CSS
gulp.task('minify-css', () => {
    return gulp.src('src/assets/css/*.css') // Chemin des fichiers CSS d'origine
        .pipe(cleanCSS()) // Compresser les fichiers CSS
        .pipe(rename({ suffix: '.min' })) // Ajouter ".min" au nom du fichier
        .pipe(gulp.dest('src/assets/css')); // Répertoire de sortie
});

// Tâche par défaut
gulp.task('default', gulp.series('clean', 'minify-js', 'minify-css'));