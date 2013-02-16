<?php
/*
 * Copyright (C) 2011-2013 Larroque Stephen
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the Affero GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.

 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 *
 */

/**
 * \description     Main configuration file for the whole application
 */

// Debug options: uncomment the following lines if you want to get more verbose outputs on your test server (AVOID on production server)
ini_set('display_errors', 1);
ini_set('error_reporting', E_ALL);

$conf = array();

// Debug mode: add more verbose output, only enable this on test servers because this can show some critical informations to hackers
$conf['debug'] = false;

$conf['maxfilesize'] = 10000000;

// no regexp
$conf['storagedir'] = 'maps/'; // maps directory (where to upload and list maps). Note: please add the '/' at the end!
$conf['thumbdir'] = 'thumbnails/'; // thumbnails and txt dir (or any other additional info in fact).  Note: please add the '/' at the end!
$conf['tempdir'] = 'tmp'; // temporary directory where to temporarily store the uploaded files before moving them
//$conf['tempdir'] = ini_get("upload_tmp_dir") . DIRECTORY_SEPARATOR . "plupload";
$conf['indexfile'] = 'bsplist.txt'; // file that will contain the list of all bsp (maps) in all uploaded pk3s + some other additional informations
$conf['logfile'] = 'logfile.txt'; // log file where to store a history of all uploading attempts.

$conf['maxtotalbytes'] = 108000000; // maximum number of bytes for all maps storage size (if above this threshold, no more map will be accepted) - set to 0 for unlimited
$conf['maxfilebytes'] = 10800000; // maximum size of one pk3. Set to 0 for unlimited.

// no regexp
$conf['blacklist_filename'] = array( 'pak0.pk3',
                                                'pak1-maps.pk3',
                                                'pak2-players.pk3',
                                                'pak2-players-mature.pk3',
                                                'pak4-textures.pk3',
                                                'pak5-TA.pk3',
                                                'pak6-patch085.pk3',
                                                'pak6-patch088.pk3',
                                                'zz-vm-oa-compatfix.pk3',
                                                ); // no regexp

// regexp
$conf['blacklist_in_pk3'] = array(   '/^\/?qvm.*/i', // forbid qvm dir at root
                                                    '/.*\.qvm.*/i', // forbid .qvm files
                                                    );

// regexp
$conf['allowed_filename'] = array(    '/^[a-zA-Z1-9].*\.pk3$/i', // engine consider pk3 files as soon as they contain .pk3, even if there's something else after. So filter out any file that contains \.pk3.* Also, we avoid any pk3 beginning by a special character such as . or .. (to avoid uploading hidden files on UNIX)
                                                                    // '/.*\.bsp$/i',
                                                                    );

$conf['clientslog'] = 'clientslog.txt'; // this file stores the IP + last uploads informations about a client, to limit the abuse
$conf['maxnbuploads'] = 2; // maximum number of SUCCESSFUL uploads per user per interval (days/weeks/etc you can configure below). Set to 0 for unlimited uploads. Note: admins automatically get unlimited downloads when logged.
$conf['maxuploadsinterval'] = 60; // interval to wait for the max number of uploads to be reinitialized (this is calculated after the first upload: firstuploadtime + maxuploadsinterval)


// Note: to generate new passwords hashs, use the provided quick-hash-generator.php script and copy/paste the Obscure hash here in $admin_passwordhash
// or you can use your own implementation, but do NOT use an online generator ! (they may store your hash)
$conf['adminhash'] = '3b4952c64cea9aa1ed95aa1f02d46705d140c54928478583298c97b415cf4d075f60f6b6229d631d5b26cd3d69b6b47a05d6447be610600628aaa05a7091a642'; // hash of your admin password (type Obscure). Do NOT put here the password, only the hash.
// default = admin

$conf['downloadhash'] = '5f4dcc3b5aa765d61d8327deb882cf99'; // Download hash (to remotely download the list of pk3 and bsp)
// default = password

// Dimensions of the map's thumbnail/levelshot (set to 0 to keep the original size, or set only one of the parameters to keep ratio, eg: width to 60 and height to 0 will keep the ratio and limit only the image's width)
$conf['thumbwidth'] = 0;
$conf['thumbheight'] = 120;

// Enable captcha at upload form (clients will have to solve a captcha once before having access to the uploading form)
$conf['captcha'] = false;
$conf['recaptcha_public_key'] = '6LdUhMsSAAAAAM8_K2TPCePopj56tz4gVWeSaTqX'; // Get a free public and private key at https://www.google.com/recaptcha/admin/create
$conf['recaptcha_private_key'] = '6LdUhMsSAAAAAMj0zwoJonEjuMY6L3dTgq7NbzCW';

?>