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
 * \description     Server script to process the uploading of a file in the background, and do the necessary security checks, and build the extended infos (index file, thumbnail, description file, etc..)
 */

// Start server-side sessions (used to recognize admin and avoid the limit of uploads)
session_start();

// Include the config file (will load a $conf[] array)
require_once(dirname(__FILE__).'/config.php');

// Include required libraries
require_once(dirname(__FILE__).'/lib.php');


// == Process the uploaded files (store them in the local temp folder)
// Note: including the file is enough, we didn't make a function because we want the script to access all variables in the global scope (TODO: we may change it to a function in the future)
if (empty($_REQUEST)) {
    die();
} else {
    include_once('mapuploader_aux.php');

    // Check if file has been uploaded
    if (isset($_REQUEST['chunks']) && isset($_REQUEST['chunk']) && $_REQUEST['chunks'] > 1 && $_REQUEST['chunk'] != $_REQUEST['chunks'] - 1) { // we just check that chunk (id of current chunk) = chunkS -1 (chunks is count of chunk, and since chunk index begin at 0, chunks = max(chunk)+1, so if chunk = chunks-1 it means it's the latest chunk, and in this case the file is uploaded and we can continue the processing normally)
        print('ChunkOK');
        die(); // if chunking is enabled and we have not yet completed the whole file, we stop here and wait for the next chunk
    }

    if ($conf['debug']) {
        print '<pre>';
        print_r($_REQUEST);
        print '</pre>';
    }
}
// Else if it's ok, we continue processing the file (the file is uploaded, now we check that it's valid and secure)

$error = 0; // error counter

// == Initializing the file
// Set the filename
$filename = $_REQUEST['name'];
$fullpath = printPath($conf['tempdir']).DIRECTORY_SEPARATOR.$_REQUEST['name'];
//print file_exists($filename);
//$fullpath = 'tmp/mkbase-dup.pk3';

// == Check the limit of successful uploads per day/week (configurable in config.php)
$sess = readIndexFile(false, $conf['clientslog']);
$sesskey = $_SERVER['REMOTE_ADDR']; // the key of the session = ip address
if (isset($sess[$sesskey]) && isset($sess[$sesskey]['firstuploadtime'])) { // check that the user already has a session and that he already uploaded once
    $remainingseconds = ($sess[$sesskey]['firstuploadtime']+$conf['maxuploadsinterval'])-time();
    if (!isset($_SESSION['auth']) or $_SESSION['auth'] !== 'yes') { // check this only if not an admin (admins get unlimited uploads)
        // If the user is over the uploads limit, we return an error and stop
        if ($remainingseconds > 0 and
            $sess[$sesskey]['nbuploads'] >= $conf['maxnbuploads']) {

            printAndLog("Error: (with $filename) you have uploaded the maximum number (".$conf['maxnbuploads'].") of files you can. Please wait ".printTime($remainingseconds)." before uploading a new file.");
            if (file_exists($fullpath)) @unlink($fullpath); // delete the temporary file if there's one
            die(); // stop processing the upload
        // Else if the time limit is expired, we remove it (and only if the time is expired! We don't want to remove when the number of uploads is below the threshold, hence the elseif instead of an else)
        } elseif ($remainingseconds <= 0) {
            // Unset all informations about this user from the clientslog
            unset($sess[$sesskey]['firstuploadtime']);
            unset($sess[$sesskey]['nbuploads']);
            unset($sess[$sesskey]);
        }
    }
}

// == Open the zip file
$archive = new PclZip($fullpath);
$ziplist = $archive->listContent();

// == Check if space is OK
if ($conf['maxtotalbytes'] > 0) {
    if ( (sizeFiles($conf['storagedir'])+filesize($fullpath)) > $conf['maxtotalbytes'] ) {
        printAndLog("Error: (with $filename) max storage space exceeded. No more maps is accepted for the moment.");
        die();
    }
}
if ($conf['maxfilebytes'] > 0) {
    if (filesize($fullpath) > $conf['maxfilebytes']) {
        printAndLog("Error: file $filename is exceeding the size limit of ".$conf['maxfilebytes']." per file.");
        die();
    }
}

// == Check file validity and moving the file
list($rtncode, $rtnmsg) = checkFile($filename, $conf['tempdir'], $ziplist);

// If error or the file is not valid, we delete it (anyway it's autocleaned after a certain amount of time by mapuploader_aux.php, but we should try to clean ASAP)
if ($rtncode < 0) {
    @unlink($fullpath);
    printAndLog("Error: $filename $rtnmsg");
    die();
// Else everything's alright, we move the uploaded file to the storagedir
} else {
    if (copy($fullpath, printPath($conf['storagedir']).'/'.$filename) === TRUE) {
        // If copying is successful, we remove the temp file
        @unlink($fullpath);
        $fullpath = printPath($conf['storagedir']).'/'.$filename; // update the full path
    } else {
        // If we can't move the file, we try to delete it and show an error message
        $error++;
        @unlink($fullpath);
        printAndLog("Error: $filename cannot move the temp file.");
        die();
    }
}

// == ReBuild bsplist index file
list($rtncode, $rtnmsg) = rebuildIndexFile();
if ($rtncode < 0) {
    printAndLog("Error: $filename $rtnmsg");
    $error++;
}

// == Thumbnail (levelshot) extraction (this has to be done after rebuilding the index file, so that extended info can be appended like the arena data)
list($rtncode, $rtnmsg) = extractThumbnailAndInfos($fullpath, $ziplist);
if ($rtncode < 0) {
    printAndLog("Error: $filename $rtnmsg");
    $error++;
}

// == Final result: if everything went alright, we print an OK message
if ($error == 0) {

    // Set the uploading count (to limit the max number of files one can upload in an interval of time), and only if the upload was successful (failed attempts do not count)
    if (!isset($sess[$sesskey]['firstuploadtime'])) { // if that's the first upload (since the reinitialization of the max nb uploads limit), then we set this time as the first upload time
        $sess[$sesskey]['firstuploadtime'] = time();
    }

    if (isset($sess[$sesskey]['nbuploads'])) { // increment the count of successful uploads for this user
        $sess[$sesskey]['nbuploads']++;
    } else {
        $sess[$sesskey]['nbuploads'] = 1;
    }

    // Write the session into a file (so that it won't get lost if the user clear his cache)
    writeIndexFile($sess, $conf['clientslog']);

    // Finally, return a successful response
    printAndLog("OK, the file $filename was successfully uploaded.");
}

// Debug
if ($conf['debug']) {
    print('<pre>');
    $bsplist = listBsp($fullpath, $ziplist);
    print_r($bsplist);
    print_r($ziplist);
    //print_r(listFilesInZip($fullpath, $ziplist));
    print('</pre>');
}

?>