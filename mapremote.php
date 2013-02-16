<?php
/*
 * Copyright (C) 2013 Larroque Stephen
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
 * \description     Remote HTTP/URL API access to manage maps (access, get maps list, get pk3 list, remove a map, rebuild the bsp/map list). There are two rights: download only, or admin (modification and index rebuild is allowed).
 */

session_start();
require_once('config.php');
require_once('lib.php');

$rights = array('admin'=>false, 'download'=>false);
if (isset($_REQUEST['password'])) {
    // User can login either by giving the download hash (more secure), or either by giving the password itself (the hash will be computed), but it's less secure because the password will be transmitted in clear
    if ( !strcmp($_REQUEST['password'], $conf['downloadhash']) or !strcmp(md5($_REQUEST['password']), $conf['downloadhash']) ) {
        $rights['download'] = true;
    // Same here for admin password
    } elseif ( !strcmp($_REQUEST['password'], $conf['adminhash']) or !strcmp(obscure($_REQUEST['password'], $conf['adminhash']), $conf['adminhash']) ) {
        $rights['admin'] = true;
    }
}

// == Rights checking
// No right found, we quit (blank page)
if ( !$rights['admin'] and !$rights['download'] and (!isset($_SESSION['auth']) or $_SESSION['auth'] !== 'yes') ) {
    die();
// Else, at least one right is enabled
} else {
    // First, we process actions that are allowed to both admin and download rights

    // Action: download BSP (map name) list
    if (isset($_REQUEST['dlbsplist'])) {
        $bsplist = readIndexFile();
        foreach ($bsplist as $bsp) {
            print $bsp['mapname']."\n";
        }

    // Action: download PK3 list
    } elseif (isset($_REQUEST['dlpk3list'])) {
        $filesList = listFiles($conf['storagedir'], '*.pk3');
        $rooturl = dirname($_SERVER['SCRIPT_NAME']).'/'.$conf['storagedir'];
        if (substr($rooturl, -1) === '/') $rooturl = substr($rooturl, 0, -1); // remove the last '/' if it's there (because we add one after, so it's better to avoid double slash)
        foreach ($filesList as $file) {
            print $rooturl.'/'.$file."\n";
        }
    }

    // Now, the actions that are only allowed to admin
    if ($rights['admin'] or (isset($_SESSION['auth']) && $_SESSION['auth'] === 'yes')) {

        // Action: rebuild the whole index file (refresh the bsp list)
        if (isset($_REQUEST['rebuild'])) {
            $result = manageError('rebuildIndexFile');
            if (!empty($result)) {
                print $result; // if error, we print it
            } else {
                print 'OK'; // if everything went alright, then there's no error message, so then we can print OK (useful for scripts)
            }
        }

        // Action: rebuild the whole index file + extract extended informations and resources like the levelshots
        if (isset($_REQUEST['rebuildfull'])) {
            $result = manageError('rebuildFull');
            if (!empty($result)) {
                print $result; // if error, we print it
            } else {
                print 'OK'; // if everything went alright, then there's no error message, so then we can print OK (useful for scripts)
            }
        }

        // Action: remove a pk3 and all the related entries in the index file
        if (isset($_REQUEST['remove']) and !empty($_REQUEST['remove'])) {
            $result = manageError('removePk3', $_REQUEST['remove']);
            if (!empty($result)) {
                print $result; // if error, we print it
            } else {
                print 'OK'; // if everything went alright, then there's no error message, so then we can print OK (useful for scripts)
            }
        }
    }
}

?>