
Base theme
==========

## Scripts

You can use these scripts directly or use the shorthands on Bolt's `do/` folder.

	# Install dependencies required for builds as defined in package.json
	npm install

	# Install frontend libraries as defined in bower.json
	bower install

	# Build assets as defined in gulpfile.js (if you've installed Gulp globally)
	gulp build

	# If you haven't
	node_modules/.bin/gulp build

	# Build and watch for changes
	gulp



## Manifests

- `bower.json`
- `config.yml`
- `gulpfile.js`
- `package.json`



## Paths

- Paths are always set relative to theme root
- JS & CSS builds go to `builds/`
- Static assets (images, fonts etc) are under `public/`
- In CSS, the path to public/ is automatically added to url()s



## Twig templates

- Variables set in `config.yml`
- Some setup work done in `setup.twig`
- Paths accessible via Bolt's `paths` variable



## Builds

- Builds done with Gulp
- Automatically scrapes for Bower components
- Scrapes `source/` for CSS and JS files
- Files appear under `build/`
