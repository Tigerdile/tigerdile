Tigerdile Web Site
==================

Introduction
------------
This is the code for Tigerdile's website.  This doesn't include the chat server, video authorization server, API server, or preview server which are a separate piece written in Python.

Being just the front end, it uses PHP with Zend Framework 2 ridigng on a MySQL database.  This document will attempt to guide you through setting up and developing; please note that this USED to be a WordPress based site, and so there's a lot of legacy WordPress crap still around that I am not terribly proud of.

What Is Swaggerdile?
--------------------
I created a service called Swaggerdile which was a patreon competitor.  At one point, we had Tigerdile running as a WordPress site and Swaggerdile running as a Zend Framework application.  I later folded Tigerdile into Swaggerdile and got rid of WordPress.  Some WordPress-y stuff remains, but mostly it is Zend Framework.

You will still see a lot of references to Swaggerdile and a lot of vistigal stuff.  For instance, there's a really cool Dropbox-like file system in Tigerdile.  I would love to actually integrate that with Patreon someday as a Patreon 'app' so that people can manage their files in a sane way -- we have a DB way to map Patreon tiers to Tigerdile tiers, and then Tigerdile tiers to content in the file browser, so we could make it really slick.  I just haven't had the time or drive to do so.

Installation
------------
The first step is to install the needed "vendor" files.  Do this with composer:

```
./composer.phar install
```

Set up your database.  Install MySQL and create a tigerdile DB thusly:

```
# Enter mySQL command prompt.  Use CTRL-D to exit the command prompt.
#
# You can create a database user for Tigerdile as well.  I can never remember
# the command line for it, so google it and update this file so the next
# person doesn't have to :D
mysql --user=root --password
create database tigerdile default charset 'utf8';
```

Tigerdile has never been run with a totally empty DB since it evolved from a WordPress installation, and we're missing certain critical admin screens that I just never wrote because they weren't important.  So there's a base DB with a bunch of fake users and profiles in it, dervied from the live DB.  All sensitive info has been scrubbed.  Import the base DB thusly:

```
# Replace your password accordingly, and your database name if you chose
# not to call your DB 'tigerdile'
gzip -dc sql/base.db.gz | mysql --user=root --password=xxx tigerdile
```

Set up your WordPress configuration file.  This config file is actually used by all of Tigerdile's subsystems, and yes, it used to be a WordPress configuration and still has the comments from that era.  I've taken out most of the stuff that doesn't matter.

```
cp tigerdile/wp-config.php.tmpl tigerdile/wp-config.php
# edit with your favorit editor
# It is well commented, you'll mostly want to change DB stuff
```

Set up your Zend Framework configuration.  This mostly just sets the right cookie domain, but you need to do it:

```
cp config/autoload/local.php.dist config/autoload/local.php
# Edit with your favorite editor
```

That should be it!


Web server setup
----------------

The easy way to do development is to use the PHP CLI server.  You can run it with the 'devserver.sh' script and it will run on 0.0.0.0 port 8080.

In production, nginx is used as a webserver.  TODO: put in nginx config files.


Known Development Issues
------------------------
The API server is currently hardcoded in place.  If you grep for 'api.tigerdile.com' you will see it is in a couple places.  We should move that to config so that you can work with a local API server rather than hitting Tigerdile's.

stream.tigerdile.com is also hardcoded in some places, as is outbound.tigerdile.com and a few other URLs.  All these should be in config.

I never did this for a combination of reasons; laziness for one, but also because when I worked on the website, I preferred it to hit the live services so it would be easier to work with.  Usually when I worked with the services, I interacted with them directly.

When I say "services", I mean the API server, Chat server, Auth Server, etc.

Coding Standards
----------------
I've tried to follow the Zend Framework coding stanards.  It is, in particular, important that every function have a doc-block "contract" preferably with annotations.  Major branches and loops should be commented.

I do not believe in self commenting code.  That's a lie that lazy developers tell themselves.  If some of my code isn't well commented, it's because I was hacking away at it at 1 AM trying to get something broken to work after working a 10 hour day as a developer.  I'll confess I've sometimes been lazy and I'll tell you, I'm really not proud of this code base.  But going forward, I really want only clean, well documented code if possible.

Please don't judge me too harshly by this body of work.  As always, I could totally do better were I to do it over.  But, that is unlikely to happen :)
