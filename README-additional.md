bolt-base
=========

Base setup for creating a new Bolt CMS project.

Still broken as hell, don't use it.

## TODO

- Template + theme
	- RSS & sitemap links
	- Split Gulp tasks into files
	- Make `gulp watch` reliable
	- ~~[CSS paths rebase](https://github.com/42Zavattas/gulp-css-url-rebase)~~
	- Don't hardcode theme name
		- Scripts
		- Readmes
- Deployment
	- Strategy that runs `./install` and not composer + hooks
	- [Satellite deployment](https://github.com/rocketeers/satellite)
- Localization
	- Labels in theme templates
	- Editable content
- Investigate Bolt update process
- Environment
	- Ensure permission scheme works (avoid sudo, specify users/groups on remote...)
		- `dbcheck_ts`
		- `files/`
		- `database`
		- `*.yml`
	- Ansible scripts?
- NextCSS
- Preprocessor support?
- Knockout
	- Build components with Gulp tasks
    - Wrap component templates in `data-component="my-component"`
    - Wrap component CSS in attr selectors
    - Print templates in script tags



## Environment requirements

- Apache/nginx
- mod_rewrite, `.htaccess` allowed
- PHP5 (plus php on command line)
- SQLite
- Node + NPM

## Scripts included

1. `do/install`: Install dependencies (Composer, NPM)
2. `do/clear`: Clear cache, check DB & update schema if needed (app/nut)
3. `do/build`: Build assets (Gulp)
4. `do/watch`: Build assets and keep watching for changes (Gulp)
5. `do/serve`: Put site on [localhost:8000](http://localhost:8000) (PHP's web server)
6. `do/update`: Update Composer, NPM and Bower dependencies.
7. `do/deploy`: Use Rocketeer to deploy the site on a remote server.
8. `do/prune`: Clean up the project from unnecessary files (including .git, on remote server after deployment)

## Credentials

- root@root.root
	- Username: root
	- Password: rootroot

## Extensions installed

- [Bolt Redirector](https://extensions.bolt.cm/view/d325689f-ace6-4700-bffd-1197d9c0cec8)
- [ColourSpectrum](https://extensions.bolt.cm/view/abf573f0-d8cc-11e4-a99b-c5c5895e3a0c)
- [JSON Access](https://extensions.bolt.cm/view/a5eb8c95-01ad-44a8-9a13-4a4ecb92acb4)
- [RSS Feed](https://extensions.bolt.cm/view/87e7ff17-31dc-4f8b-bc4a-05159b8293a3)
- [Sitemap](https://extensions.bolt.cm/view/e89b81c7-bbd3-4221-82b9-070ba6680c45)



Project setup
=============

Set up a new project:

	git clone git@bitbucket.org:Eiskis/bolt-base.git myproject
	cd myproject
	do/install
	do/build
	do/serve

Site should be up at [localhost:8000](http://localhost:8000).

[Log in](http://localhost:8000/bolt) as `root`:`rootroot` and change the password.

Configure the site with the `app/config/*.yml` files. `do/clear` if you edit content types or taxonomies.

## Theme development

The *theme/base/* is really your playground. Start with *theme/base/config/*.

`do/watch` for changes to auto build CSS and JS with Gulp as you make changes.

Add dependencies locally like so:

	cd theme/base/
	npm install gulp-plugin --save

	# If you have bower installed locally
	bower install bower-component --save

	# If you don't have bower installed locally
	./node_modules/.bin/bower install bower-component --save

The manifests will be committed. Upon deployment, these will be installed on remote independently.

## Deployment

When you're ready to deploy, set the repo and remote details in the *.rocketeer/* config files.

Rocketeer will connect to a server, pull a copy of the latest version, install and build, and then point a symlink to the new release. It will share uploaded files and database across versions, and database schema is automatically updated.



Environment setup
=================

Setting up a server on vanilla Ubuntu? Here's (roughly) what you need to do.

	# Update lists
	sudo apt-get update
	sudo apt-get upgrade

	# Install Apache and sqlite
	sudo apt-get install apache2 libapache2-mod-php5 sqlite3 libsqlite3-dev
	sudo apachectl restart

	# Add source of latest version for PHP
	sudo add-apt-repository ppa:ondrej/php5-5.6
	sudo apt-get update
	sudo apt-get install python-software-properties

	# Install PHP + extensions
	sudo apt-get install php5 php5-gd php5-sqlite php5-curl php5-cli php5-cgi php5-dev php5-json php5-mcrypt

	# Deployment needs Git
	sudo apt-get install git

	# And Node/NPM
	sudo apt-get install -y nodejs
	sudo apt-get install -y npm
	sudo ln -s /usr/bin/nodejs /usr/bin/node

## Apache settings

Check the *document root* and allow overrides in `/etc/apache2/sites-available/000-default.conf`:

	<Directory /var/www/>
		DocumentRoot /var/www/
		AllowOverride All

Some Apache settings as good practice in `/etc/apache2/apache2.conf`:

	ServerTokens Prod
	ServerSignature Off

Enable Apache's rewrite module:

	sudo a2enmod rewrite
	sudo service apache2 restart

## Hopefully not needed

Everything works best when Apache runs as the same user as who logs in during deployment (e.g. `ubuntu`). The deploy target folder should be writable by the user that logs in during deployment.

	sudo nano /etc/apache2/envvars

	export APACHE_RUN_USER=ubuntu
	export APACHE_RUN_GROUP=ubuntu

	sudo service apache2 restart
	sudo chown ubuntu:ubuntu -R /var/www
	sudo chgrp ubuntu /var/www
	sudo chmod u+s /var/www
	sudo chmod g+s /var/www
	mkdir /var/www/<deploytarget>
