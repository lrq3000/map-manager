MAP-MANAGER README v1.1.2
=======================
Map-Manager is a PHP5 web application to let users upload, list and manage maps that will then be uploaded onto your game server (any ioquake3 compatible game, eg: OpenArena, ioUrban Terror, Smoking Guns, Enemy Territory, Warsow, Tremulous, etc..)

DESCRIPTION
-----------

This software is a PHP5 web application designed to offer the clients the possibility to securely and flexibly upload maps on your game server via a secure web form.

It is designed to work for Quake 3 Arena and ioquake3 compatible games (eg: OpenArena, Urban Terror, Smoking Guns, PadMan, Warsow, Tremulous, Enemy Territory, etc..). This may also work for games based on other engines with little modifications (except if the maps are not zip compressed, in this case you'll have to plug in your own archive reading library).

The whole application is designed to be the most secure possible and avoid abuses.

Secure in the sense that the pk3 should be safe to use, containing no qvm and no overwrites of existing maps, thus it should not modify unexpectedly the behaviour of your game server (and hopefully avoid hacking by fake map uploading).

AUTHOR
------

This software was developped by Stephen Larroque.

You can contact the author at <lrq3000 at gmail dot com>

LICENSE
-------

This software is licensed under the Affero GNU General Public License v3 and above (AGPLv3+).

This software uses the PclZip Library under the LGPLv2.1+, the Plupload library under the GPLv2 and the tga.php library by de77 (de77.com) under the MIT license.

INSTALL
-------

Just copy every files inside the archive (along this README) to any folder you want on your web server. Your server must run PHP > 5 (it may work below but this wasn't tested).

The default configuration is made for OpenArena, but you can modify it to suit your needs by editing the file: config.php

Optionally, if you want to use the captcha protection, you can get a reCaptcha key: https://www.google.com/recaptcha/admin/create

USAGE
-----

- For the clients to upload maps, just access: mapuploaderform.php
- For the clients to see the list of uploaded maps: maplister.php
- For admin access (unlimited uploads and quick commands to delete maps or rebuild the index): admin.php
- For remote administration and fetching the maps listing, use: mapremote.php
- To manage the transfert of the maps from the webserver to your game server, a sample script is provided in samples/mapsync.py.

This software is designed for both the user and administrator in mind. When new maps are uploaded, the administrator can check the list of maps and download the new maps by using the mapremote.php file and a "download password" so that only the admin (or the people having this password) can get the maps list.

A simple HTTP GET on mapremote.php will allow you to remotely manage the software.

Eg:
mapremote.php?password=$yourdlpassword&dlpk3list

The list of available commands in mapremote.php is as follow:
- password: can either be your download password, or your admin password. Either one will open different rights and possible commands (admin has access to download commands too).
Note: you can also use your password hash to avoid transmitting in clear your password over the network. Eg: password=hash_of_download_or_admin_password
- dlbsplist: show the list of all maps/bsp (contained inside the pk3, so you can't just download them right away with only this list. To download, see "dlpk3list" below).
- dlpk3list: show the list of all pk3 files with full path from the root domain (you can then download them with a remote script like rsync or your own using wget).
- rebuild: rebuild from scratch the bsplist index file
- rebuildfull: same as rebuild + also extracts all extended resources like the levelshot, long description, arena file, etc...
- remove: delete a pk3 from the server and update the bsplist index file. Eg: mapremote.php?remove=pak0.pk3

Advices:
- you can (and should!) put this script on another web server than your game server. This will allow for a better load balancing (the user uploading/downloading maps from the web server won't slow down the players on the game server). It is also advised in your game server to allow and set an HTTP download pointing to your webserver (eg: in OpenArena use set /sv_dlURL "your_web_adress").
- A remote script should be used on your game server to synchronize the maps between the uploaded maps and your game server's maps. Eg: rsync via FTP, or just make your own bash script using wget (wget the dlpk3list and then make the difference to know what's new and then wget the maps you miss on your game server). The script samples/mapsync.py implements a similar strategy to wget, so you can use this script if you're comfortable with Python.
- A cron job should be set to regularly rebuild (or even rebuildfull) the bsplist index.
- The admin/mapremote.php remove command should be used to delete maps rather than deleting manually using the FTP. Using the remove command will ensure that the bsplist index file will always be up-to-date. As an alternative, if you really need to be able to manually delete the maps, be sure to rebuild the index file just after, or at least set a cron job to do that.

HOW IT WORKS INTERNALLY
-----------------------

To avoid abuses, a few index files are created and maintained:

- bsplist.txt contains the list of all uploaded maps (by walking inside every zip/pk3 files).
- clientslog.txt contains a count of the number of uploads for every client, to limit the number of uploads per a set interval.
- logfile.txt is a log of all uploads attempts (only for administration purposes).

At every upload, these files are updated, and also additional resources are extracted like the levelshot, long description, arena file, etc...

In addition to these index files, you can configure in the configuration file config.php a few variables to tweak the security to your needs, like blacklisting/whitelisting by regular expression or limiting the number of attempts, or activation of a captcha prior to accessing the uploading form (so that the user won't have to enter a captcha everytime they upload a file).

ADDITIONAL NOTES
----------------

This software uses the nice PclZip library by Vincent Blavet. You can easily update the zip library very simply by downloading a new release from the website: http://www.phpconcept.net

Thank's to Michal Migurski for the JSON library (which works on both php 4 and 5 versions, that's great!).

This software uses the Plupload library to manage the nice AJAX uploading form. This library is well done, compatible with nearly all browsers and a wide array of technologies, is very simple to use and has a lot of configuration options.

Thank's too to de77, the author of the tga.php library (the only really working pure php library to read tga images that I could find on the net). Check out his website: de77.com
