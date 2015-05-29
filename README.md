
Base
====

Base setup for creating a new Bolt CMS project.

Still broken as hell, don't use it.

## TODO

- Template + theme
	- ~~Bower for theme deps~~
	- ~~Gulp + Bower integration~~
	- RSS & sitemap links
	- Template cleanup
	- Split Gulp tasks into files
	- [CSS paths rebase](https://github.com/42Zavattas/gulp-css-url-rebase)
	- Don't hardcode theme name
		- Scripts
		- Readmes
	- Localization
- Deployment
	- ~~Base confs~~
	- [Satellite deployment](https://github.com/rocketeers/satellite)
	- Strategy that runs `./install` and not composer + hooks
- Bin
	- ~~No bins in repo~~
	- ~~Fetch bins in scripts when needed upon install~~
- Bolt update process?
- Environment
	- ~~Document Apache config~~
	- Ensure permission scheme works (avoid sudo, specify users/groups on remote...)
		- dbcheck_ts
		- files/
		- database
		- *.ymls
	- Ansible? Docker?
- Default content types
	- Pages
	- Snippets
- paths
- nextcss
- recipes on top level
- preprocessor support?
- ko gulp tasks
    - add attr data-component="my-component"
    - wrap css



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



Bolt
====

Sophisticated, lightweight & simple CMS. Homepage: [Bolt.cm](https://bolt.cm)

Bolt is a tool for Content Management, which strives to be as simple and
straightforward as possible. It is quick to set up, easy to configure, uses
elegant templates, and above all: It's a joy to use. Bolt is created using
modern open source libraries, and is best suited to build sites in HTML5 with
modern markup.

From a technical perspective: Bolt is written in PHP, and uses either SQLite,
MySQL or PostgreSQL as a database. It's built upon the [Silex framework](http://silex.sensiolabs.org)
together with a number of [Symfony](http://symfony.com/) [components](http://symfony.com/components)
and [other libraries](http://docs.bolt.cm/credits). Bolt is released under the
open source [MIT-license](http://opensource.org/licenses/mit-license.php).


Build status, code quality and other badges
-------------------------------------------

[![Build Status](https://secure.travis-ci.org/bolt/bolt.png?branch=master)](http://travis-ci.org/bolt/bolt)
[![Scrutinizer Continuous Inspections](https://scrutinizer-ci.com/g/bolt/bolt/badges/general.png?s=74400dd068f81fe3ba434e5952b961bb83bbea62)](https://scrutinizer-ci.com/g/bolt/bolt/)
[![SensioLabsInsight](https://insight.sensiolabs.com/projects/4d1713e3-be44-4c2e-ad92-35f65eee6bd5/mini.png)](https://insight.sensiolabs.com/projects/4d1713e3-be44-4c2e-ad92-35f65eee6bd5)

For continuously inspecting our code, we use Scrutinizer CI. You can find all
runs on our code base [here](https://scrutinizer-ci.com/g/bolt/bolt/inspections).

[![Bitdeli Badge](https://d2weczhvl823v0.cloudfront.net/bolt/bolt/trend.png)](https://bitdeli.com/free "Bitdeli Badge")
[![Gitter](https://badges.gitter.im/Join Chat.svg)](https://gitter.im/bolt/bolt?utm_source=badge&utm_medium=badge&utm_campaign=pr-badge&utm_content=badge)


Installation
------------

Detailed instructions can be found in the [Installation section in the documentation](http://docs.bolt.cm/installation).

Try bolt in [Ubuntu](https://manageacloud.com/cookbook/tijit2bpp3129rdctb81f1cflk/deploy#test_deployment), [CentOS](https://manageacloud.com/cookbook/nt1pf9254cg8mm1t4k0nv96jv5/deploy#test_deployment), [Debian](https://manageacloud.com/cookbook/oj5dbkcehg9h7497fjq2lagk66/deploy#test_deployment) or [Amazon Linux](https://manageacloud.com/cookbook/a382qtma5gq1928ofrsrncr70c/deploy#test_deployment).

Deployable configuration examples for [Ubuntu](https://manageacloud.com/cookbook/bolt_cms_ubuntu_utopic_unicorn_1410), [CentOS](https://manageacloud.com/cookbook/bolt_cms_centos_7), [Debian](https://manageacloud.com/cookbook/bolt_cms) and  [Amazon Linux](https://manageacloud.com/cookbook/bolt_cms_amazon_2014032)

Reporting issues
----------------
When you run into an issue, be sure to provide some details on the issue.
Please include with your report:
- the (example) input;
- the output you expected;
- the output actually produced.

This way we can reproduce your issue, turn it into a test and prevent the issue
from occurring in future versions.

Unit tests
----------
For running unit tests you need [phpunit](http://www.phpunit.de/).

After installing, you can run the unit test suite by running:

    $ phpunit

This can now also be done by using app/nut:

    $ php app/nut tests:run

Extensions and Themes
---------------------
Since Bolt 2.0, you can install extensions and themes directly from Bolt's
interface. To browse the available extensions and themes, visit
[extensions.bolt.cm](https://extensions.bolt.cm).

-------
