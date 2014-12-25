# Phoenix
Simple, Efficient and Fast BitTorrent Tracker

Based on [PeerTracker](https://github.com/JonnyJD/peertracker)

## What Do You Need?

### Required
* Apache, Nginx, OR lighttpd.
* PHP >= 5
* MySQL >= 4.1

### Recommended
* PHP >= 5.3
* MySQL >= 5.1 OR MariaDB >= 5

## Install Guide
1. upload ./help.php to your tracker's document directory.
2. followed by uploading all of the files 'inside' of the ./mysql/ directory as well.
3. edit the configuration section at the top of the tracker.mysql.php file.
4. run ./help.php from your browser and install the tracker database
5. remove ./help.php and you have now installed the MySQL edition of Phoenix.

These same steps should be followed for whichever database system you choose to use.

## Step by Step Install Guide
1. upload ./help.php to your tracker's document directory.
2. run the uploaded script from your site.
   * example:
     * http://tracker.yoursite.com/help.php
3. check out the information, this will let you know what your server supports.
4. after deciding which database system you would like to use, upload the files 'inside'
   of the respectively named directory to your trackers top web accessible directory.
   * example:
     * http://tracker.yoursite.com/announce.php
     * http://tracker.yoursite.com/scrape.php
     * etc...
5. edit the configuration file. it will contain all of the settings needed to run
   the tracker, such as path to the database, host, user, pass, port etc. it will
   be named according to the database being used.
   * example:
     * http://tracker.yoursite.com/tracker.sqlite3.php
     * http://tracker.yoursite.com/tracker.mysql.php
     * etc...
6. included are .htaccess files, they help Phoenix to support the typical url
   format ie. http://tracker.your.site/announce (notice, no .php extension). not
   all webservers fully support these files, either because they don't recognize
   them or because they have them disabled; if you notice them causing any problems
   just remove them, they're not necessary for successful tracker operation.
7. run the ./help.php file again, and proceed to install the tracker database.
   * example:
     * http://tracker.yoursite.com/help.php
8. delete ./help.php from your tracker's document directory.
9. finished tracker setup.
   * now, you can use the following url for tracking:
     * http://tracker.yoursite.com/announce
   * or the extended url, if your server doesnt support .htaccess files:
     * http://tracker.yoursite.com/announce.php

## Misc Credits:
The project icon is (to my knowledge) licensed under the Creative Commons,
Attribution-Noncommercial-No Derivative Works 3.0. Whomever designed it,
feel free to contact me and I will give appropriate credit to said person.