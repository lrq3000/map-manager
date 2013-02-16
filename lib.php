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
 * \description     Main library for map management and various utilities
 */

// Include zip files management library PCLZip (works on many versions of PHP)
require_once('lib/pclzip.lib.php');

// Include JSON library for PHP <= 5.2 (used to define json_decode() and json_encode() for PHP4)
if( !function_exists('json_decode') or !function_exists('json_encode') ) include_once(dirname(__FILE__).'/lib/JSON.php');

// Include password hash generation/checking library (cryptographic library)
include_once('lib-crypt.php');

// Nomenclatura for naming:
// * function: action first, then object. Eg: listFiles, the opposite for the variable since it's not a verb anymore but an object: filesList

// Nearly all functions return an array, with the errorcode and errormessage

// Get the list of files in a directory (no recursive and only files, no directory is returned)
function listFiles($dir) {
    $files = array_diff(scandir($dir), array('.','..'));
    $result = array();
    foreach ($files as $file) {
        if ( !is_dir("$dir/$file") ) $result[] = $file;
    }
    return $result;
}

// Get the list of files, with the full pathname
// TODO: * does not return hidden files (beginning with .) on UNIX/LINUX
function listFilepath($dir, $filter='*') {
    //get all files in specified directory
    return glob($dir . "/$filter", GLOB_NOESCAPE | GLOB_MARK);
}

// Count the number of files in a directory recursively
function countFiles($path) {
    $count = 0;
    $dircount = 0;
    $files = glob($path);
    if (empty($files)) return 0; // return 0 if there's no file in this folder!
    foreach ($files as $filename) {
        if (!empty($filename) and is_dir($filename)) {
            ++$dircount;
            list($count2, $dircount2) = countFiles($filename . '/*');
            $count += $count2;
            $dircount += $dircount2;
        } elseif (is_file($filename)) {
            ++$count;
        }
    }
    return array($count, $dircount);
}

// Count the size of all files recursively, starting from a given path (directory)
function sizeFiles($path) {
    $count = 0;
    $files = glob($path);
    if (empty($files)) return 0; // return 0 if there's no file in this folder!
    foreach ($files as $filename) {
        if (!empty($filename) and is_dir($filename)) {
            $count += sizeFiles($filename . '/*');
        } elseif (is_file($filename)) {
            $count += filesize($filename);
        }
    }
    return $count;
}

// Check that a given file is valid for uploading against a set of rules
// @return  array($rtncode, $rtnmsg) where $rtncode <0 KO, >=0 OK and $rtnmsg either empty or the error message
function checkFile($filename, $dir=null, $ziplist=null) {
    global $conf;

    // == Construct the fullpath to the file
    if (!empty($dir)) {
        $fullpath = printPath($dir).DIRECTORY_SEPARATOR.$filename;
    } else {
        $fullpath = $filename;
    }

    // == Check file existence
    if (!(file_exists($fullpath)) ) return array(-1, "File $fullpath not found.");

    // == Read the list of compressed files in the zip if the list is not already supplied
    if (!isset($ziplist)) {
        $archive = new PclZip($fullpath);
        $ziplist = $archive->listContent();
    }

    // == Check that the zip is really a zip and not empty
    if (empty($ziplist)) return array(-1, "The file is empty or is not a valid pk3 zipfile.");

    // == Check blacklist filename
    if (!empty($conf['blacklist_filename'])) {
        foreach ($conf['blacklist_filename'] as $bword) {
            if ( stripos($filename, $bword) !== FALSE ) return array(-1, "This filename ($filename) is not allowed");
        }
    }

    // == Check allowed filename (whitelist)
    if (!empty($conf['allowed_filename'])) {
        $valid = false;
        foreach ($conf['allowed_filename'] as $aword) {
            if ( preg_match($aword, $filename) === 1 ) {
                $valid = true;
                break;
            }
        }

        // Check result
        if (!$valid) return array(-1, "The filename $filename is not of a valid format: ".implode(' or ', $conf['allowed_filename']));
    }

    // == Check blacklist in pk3
    if (!empty($conf['blacklist_in_pk3'])) {
        // TODO: should check if an almost infinite recursive folder zipped can prevent the function from working correctly and the file to be uploaded after the error?
        $valid = false; // in case there's an error, by default the file can't be uploaded
        try {
            $valid = true;
            // For each compressed file in the zip
            foreach($ziplist as $cfile) { // $cfile = compressed file
                // Check every blacklist word against the filename
                foreach ($conf['blacklist_in_pk3'] as $bword) {
                    // If it matches, we break and stop the uploading
                    if ( preg_match($bword, $cfile['filename']) === 1 ) {
                        $valid = false;
                        $offender = $cfile['filename'];
                        break 2;
                    }
                }
            }
        // If an error happened, we break and won't upload the file
        } catch(Exception $e) {
            $valid = false;
            $offender = $e;
        }

        // Check the result
        if (!$valid) return array(-1, "A filename in your pk3 zipfile is not allowed: $offender");
    }

    // == Check duplicates
    $fileslist = listFiles($conf['storagedir']);
    foreach ($fileslist as $file) {
        if ( !strcmp(strtolower($filename), strtolower($file)) ) return array(-1, "File $filename is a duplicate, please use another name.");
    }

    // == Check bsp duplicates
    $bsplist = listBsp($fullpath);
    $indexlist = readIndexFile();

    // No BSP (no map)? Then error
    if (empty($bsplist)) return array(-1, "No map (bsp) found in your pk3.");

    $valid = true;
    // We compare each bsp in the new uploaded pk3, against every bsp we have in all our pk3 so far.
    if (!empty($indexlist) and !empty($bsplist)) { // only check if the indexlist exists (else it means we have 0 map, no need to check)
        foreach($bsplist as $mapname=>$bsp) {
            foreach($indexlist as $refmapname=>$refbsp) {
                // Compare both the name and the crc. If at least one match, then it's a duplicate
                if (!strcmp($mapname, $refmapname) or $bsp['crc'] == $refbsp['crc'] or $bsp['size'] == 0) {
                    $valid = false;
                    $offender = $bsp['filename'];
                    break 2;
                }
            }
        }
    }

    // Check the result
    if (!$valid) return array(-1, "A bsp map in your pk3 zipfile is a duplicate or empty: $filename/$offender");

    // == Final result
    // If everything ran OK
    return 0;
}

// List all bsp/maps contained inside a pk3
function listBsp($filename, $ziplist=null) {
    global $conf;

    // == Check file existence
    if (!(file_exists($filename)) ) return array();

    // == Open the zip file
    $archive = new PclZip($filename);

    // == Read the list of compressed files in the zip if the list is not already supplied
    if (!isset($ziplist)) {
        $ziplist = $archive->listContent();
    }

    // If there's nothing at all in the zip file, we return an empty array
    if (empty($ziplist)) return array();

    // == Find all the bsp maps
    $bsplist = array();
    $filename_parts = pathinfo($filename); // remove the file extension (stored inside 'filename' index)
    foreach ($ziplist as $cfile) {
        // Extract levelshot (map thumbnail)
        if ( preg_match('/.*\/(([^.]+)\.bsp)/i', $cfile['filename'], $match) === 1 ) {
            $bsplist[$match[2]] = array( 'mapname'=>$match[2],
                                                                 'pk3'=>$filename_parts['basename'],
                                                                 'filename'=>$cfile['filename'],
                                                                 'size'=>$cfile['size'],
                                                                 'index'=>$cfile['index'],
                                                                 'crc'=>$cfile['crc'],
                                                                 );
        }
    }

    return $bsplist;
}

// List all bsp/maps from all pk3 in a directory
function listAllBsp($dir=null) {
    global $conf;

    if (empty($dir)) $dir = $conf['storagedir'];

    $filesList = listFiles($dir);

    $bsplist = array();
    foreach ($filesList as $file) {
        $bsplist = array_merge($bsplist, listBsp("$dir/$file"));
    }

    return $bsplist;
}


// Extract extended informations and resources for all maps/bsp inside a pk3 (eg: levelshot, description/readme, .arena file)
// Note: this function works by comparing the name of a bsp to any other file in the same pk3, and trying to find files with a similar name. This is also how the ioquake3 engine works because there's no index in the pk3, BUT it may not work for other games!
// TODO: maybe try to make a facade function that will plug differently working functions according to the game engine? This will allow to define functions that will work with other game engines that store index files/metadata files.
function extractThumbnailAndInfos($filename, $ziplist=null, $bsplist=null) {
    global $conf;

    // == Check file existence
    if (!(file_exists($filename)) ) return array(-1, "File $filename not found.");

    // == Open the zip file
    $archive = new PclZip($filename);

    // == Read the list of compressed files in the zip if the list is not already supplied
    if (!isset($ziplist)) {
        $ziplist = $archive->listContent();
    }
    if (empty($ziplist)) return null;

    // == Get the list of BSP maps inside this pk3 (if not given in arguments)
    if (!isset($bsplist)) {
        $bsplist = listBsp($filename, $ziplist); // this bsplist is critical and is used to compare and get the extended informations
    }
    if (empty($bsplist)) return null;

    // == Get the list of all bsp maps in our storagedir (reading from the bsplist index)
    $indexlist = readIndexFile(); // this is only used to update the bsplist index with extended informations

    // == Find the Levelshot (map thumbnail) and extended informations
    //$filename_parts = pathinfo($filename); // remove the file extension (stored inside 'filename' index)
    // Preparing the image extensions filter
    $imgextarr = array();
    foreach ($conf['allowed_image_extensions'] as $imgext) {
        $imgextarr[] = preg_quote($imgext); // for each extension, we protect the special characters (like the .)
    }
    $image_extensions = implode('|', $imgextarr); // implode and separate with a | (OR)
    // Loop through all compressed files in the zip
    foreach ($ziplist as $cfile) {
        // Loop through all bsp maps from this pk3. We will then compare and try to find a few files with the same name (this is the only way we have to identify the files that are directly related with the map, and this is also how the ioquake3 engine works since there is no index defined in the pk3; this may not be the case for other games!)
        foreach ($bsplist as $mapname=>$additionalinfos) {

            // Extract levelshot (map thumbnail image)
            if ( preg_match('/^\/?levelshots\/('.$mapname.'('.$image_extensions.'))$/i', $cfile['filename'], $match) === 1 ) {
                // First remove the file if it exists, because pclzip->extractByIndex() does not overwrite
                if (file_exists($conf['thumbdir'].'/'.$match[1])) @unlink($conf['thumbdir'].'/'.$match[1]);
                // Extract and move the file at the same time
                $rtncode = $archive->extractByIndex($cfile['index'], $conf['thumbdir'], 'levelshots'); // levelshots will be removed from the path, else the fullpath will be extracted, not just the image (it will create thumbnails/levelshots/yourimage.jpg instead of just thumbnails/yourimage.jpg)
                // Check the error code
                if ($rtncode === 0) return array(-1, 'Couldn\'t extract the map\'s levelshot thumbnail: '.$cfile['filename']);
                // If it's a tga, we can't just show it in a web browser. So we must first convert it to jpg
                if ($match[2] === '.tga') {
                    $imgpath = $conf['thumbdir'].'/'.$match[1]; // path to the tga file
                    tga2jpg($imgpath); // convert and save a .jpg file with the same basename
                    @unlink($imgpath); // delete the old tga file
                }

            // Extract .txt extended description file
            } elseif ( preg_match('/^(.*)('.$mapname.'\.txt)/i', $cfile['filename'], $match) === 1 ) { // either we look for a mapname.txt description file...
                // First remove the file if it exists, because pclzip->extractByIndex() does not overwrite
                if (file_exists($conf['thumbdir'].'/'.$match[2])) @unlink($conf['thumbdir'].'/'.$match[2]); // Accessorily, it also serves to remove a previous readme if we find a specific description file for this map (see below)
                // Extract and move the file at the same time
                $rtncode = $archive->extractByIndex($cfile['index'], $conf['thumbdir'], $match[1]);
                // Check the error code
                if ($rtncode === 0) return array(-1, 'Couldn\'t extract the description file: '.$cfile['filename']);

            // Extract readme as .txt extended description file (but only if a specific description file for the map was not found)
            } elseif ( preg_match('/^(README(.txt)?)$/i', $cfile['filename'], $match) === 1 ) { // ...or either a README at the root
                // First remove the file if it exists, because pclzip->extractByIndex() does not overwrite
                if (file_exists($conf['thumbdir'].'/'.$match[1])) @unlink($conf['thumbdir'].'/'.$match[1]); // Accessorily, it also serves to remove a previous readme if we find a specific description file for this map (see below)
                // Extract and move the file at the same time
                $rtncode = $archive->extractByIndex($cfile['index'], $conf['thumbdir']);
                // Check the error code
                if ($rtncode === 0) return array(-1, 'Couldn\'t extract the description file: '.$cfile['filename']);
                // if the file is named README, we rename it to the same filename as the bsp
                $readmetemp = $conf['thumbdir'].'/'.$match[0];
                // We are not sure that the readme is the description file for the map, or for the whole pk3: we can have both at the same time. In this case, we give the priority to the specific description for the map, and delete the readme. Else we use the readme as the description (and anyway it can then be overwritten if we later find a specific description for the map).
                if (file_exists($conf['thumbdir'].'/'.$mapname.'.txt')) {
                    @unlink($readmetemp); // delete the readme
                } else { // else, at this moment of the loop, the readme is the only description we have for the map, so we keep it
                    @rename($readmetemp, $conf['thumbdir'].'/'.$mapname.'.txt');
                }

            // Extract .arena short description file
            } elseif ( preg_match('/^(.*)('.$mapname.'\.arena)/i', $cfile['filename'], $match) === 1 ) {
                // First remove the file if it exists, because pclzip->extractByIndex() does not overwrite
                if (file_exists($conf['thumbdir'].'/'.$match[2])) @unlink($conf['thumbdir'].'/'.$match[2]);
                // Extract and move the file at the same time
                $rtncode = $archive->extractByIndex($cfile['index'], $conf['thumbdir'], $match[1]); // we match and remove everything in the path prior to the file (ie: any parent folder, so that we just extract this file only)
                // Check the error code
                if ($rtncode === 0) {
                    return array(-1, 'Couldn\'t extract the arena file: '.$cfile['filename']);
                // If copying is OK, then we also parse and extract the extended informations
                } else {
                    // Load the .arena file
                    $arenatext = file_get_contents(printPath($conf['thumbdir']).'/'.$mapname.'.arena');
                    // Strip out the comments
                    $arenatext = preg_replace('|//[^\n]*|i', '', $arenatext);
                    // Parse the .arena file
                    preg_match_all('/(\w+)\s+"([\w\s]+)"/im', $arenatext, $matchs, PREG_SET_ORDER);
                    // Store each piece of information into the $bsplist
                    foreach ($matchs as $match) {
                        // Set the key only if it does not exists (else it could be used to hack the system, by overwriting intentionally a key value like mapname or crc)
                        if (!isset($indexlist[$mapname][$match[1]])) $indexlist[$mapname][$match[1]] = $match[2];
                    }
                }
            }
        }
    }

    // Update the index file (in case it was updated...)
    writeIndexFile($indexlist);

    return 0;
}

// Returns the list of of all the files inside a zip file, neatly formatted in an 1D array
function listFilesInZip($filename, $ziplist=null) {
    // == Check file existence
    if (!(file_exists($filename)) ) return array(-1, "File $filename not found.");

    // == Read the list of compressed files in the zip if the list is not already supplied
    if (!isset($ziplist)) {
        $archive = new PclZip($filename);
        $ziplist = $archive->listContent();
    }

    // == Get the list of files from the zip
    $filesList = array();
    foreach ($ziplist as $cfile) {
        // Avoid directories
        if ( strcmp(substr($cfile['filename'], -1), '/') ) {
            $filesList[] = $cfile['filename'];
        }
    }

    return ($filesList);
}

// Pretty print with indentation a json string. All credits go to Kendall Hopkins
// Note: there is a new option available natively in PHP 5.4, but meanwhile, this function works for all php versions
function json_prettyPrint( $json )
{
    $result = '';
    $level = 0;
    $prev_char = '';
    $in_quotes = false;
    $ends_line_level = NULL;
    $json_length = strlen( $json );

    for( $i = 0; $i < $json_length; $i++ ) {
        $char = $json[$i];
        $new_line_level = NULL;
        $post = "";
        if( $ends_line_level !== NULL ) {
            $new_line_level = $ends_line_level;
            $ends_line_level = NULL;
        }
        if( $char === '"' && $prev_char != '\\' ) {
            $in_quotes = !$in_quotes;
        } else if( ! $in_quotes ) {
            switch( $char ) {
                case '}': case ']':
                    $level--;
                    $ends_line_level = NULL;
                    $new_line_level = $level;
                    break;

                case '{': case '[':
                    $level++;
                case ',':
                    $ends_line_level = $level;
                    break;

                case ':':
                    $post = " ";
                    break;

                case " ": case "\t": case "\n": case "\r":
                    $char = "";
                    $ends_line_level = $new_line_level;
                    $new_line_level = NULL;
                    break;
            }
        }
        if( $new_line_level !== NULL ) {
            $result .= "\n".str_repeat( "\t", $new_line_level );
        }
        $result .= $char.$post;
        $prev_char = $char;
    }

    return $result;
}

// Rebuild the whole index file (bsp/maps list) from scratch, by looping through all the pk3's
function rebuildIndexFile($dir=null) {
    global $conf;

    try {
        // Get the list of all bsp from all pk3 files
        $bsplist = listAllBsp($dir=null);
        // Sort by key (bsp name)
        ksort($bsplist, SORT_STRING | SORT_ASC);
        // Write everything in the index file
        writeIndexFile($bsplist);
    } catch(Exception $e) {
        return array(-1, $e);
    }

    return 0;
}

// Rebuild the whole index file + extract extended infos and resources like the levelshots
function rebuildFull($dir=null) {
    global $conf;

    // By default, we work on the maps stored in the global storagedir
    if (empty($dir)) $dir = $conf['storagedir'];

    try {
        // Rebuild the bsplist index
        rebuildIndexFile($dir);

        // Clean up (delete) all files in the thumbdir
        $filesList = listFiles($conf['thumbdir']);
        if (!empty($filesList)) {
            foreach ($filesList as $file) {
                @unlink($conf['thumbdir'].'/'.$file);
            }
        }

        // Extract all extended resources from all pk3 for all maps in our bsplist
        $filesList = listFiles($dir);
        if (!empty($filesList)) {
            foreach ($filesList as $file) {
                extractThumbnailAndInfos($dir.'/'.$file);
            }
        }

    // Exception handling
    } catch(Exception $e) {
        return array(-1, $e);
    }

    return 0;
}

// Writes a list of bsp inside an index file as a json-encoded string
function writeIndexFile($bsplist, $indexfile=null) {
    global $conf;

    if (empty($indexfile)) $indexfile = $conf['indexfile'];

    // Read the old bsp (only necessary if we have to rollback after in case of an error)
    $oldbsplist = readIndexFile(true, $indexfile); // do not json_decode, we don't want to decode then reencode when we rollback, we can save it as-is

    // Try to write the new bsplist
    $e = 0;
    try {
        $fh = fopen($indexfile, 'w');
        $rtncode = fwrite($fh, json_prettyPrint(json_encode($bsplist)));
        fclose($fh);

    // Failure: Rollback to the previous state
    } catch(Exception $e) {
        $fh = fopen($indexfile, 'w');
        fwrite($fh, $oldbsplist);
        fclose($fh);

        $rtncode = -1; // set the error code to -1
    }

    return array($rtncode, $e);
}

// Read from the index file, the list of all bsp and returns it
function readIndexFile($nojsondecode=false, $indexfile=null) {
    global $conf;

    if (empty($indexfile)) $indexfile = $conf['indexfile'];

    if (!file_exists($indexfile)) return null;

    if ($nojsondecode) {
        $bsplist = file_get_contents($indexfile);
    } else {
        $bsplist = json_decode(file_get_contents($indexfile), true);
    }

    return $bsplist;
}

// Delete a pk3 while updating the bsplist index and deleting all associated resources in the thumbdir
// Note: a pk3 should never be deleted manually, but always through this function. Else you will have an outdated bsplist, showing ghost maps that do no longer exist on the server!
function removePk3($filename) {
    global $conf;

    $dir = $conf['storagedir'];
    $thumbdir = $conf['thumbdir'];

    // First check that there's no directory traversal attempt
    list($errcode, $errmsg) = checkDirectoryTraversal($dir, $dir . '/' . $filename);
    if ($errcode != 0) return array(-1, $errmsg);

    // Check that the file existts
    if (!(file_exists($dir.'/'.$filename)) ) return array(-1, "File $filename not found.");

    // Delete the file while updating the bsplist index
    try {
        // Get the bsplist index
        $bsplist = readIndexFile();

        // Search in the bsplist index for all bsp that are contained in this pk3, and remove those occurrences
        $nbsplist = array(); // this will store all the bsp from all pk3 except the bsp that are contained in the pk3 we are removing
        $rbsplist = array(); // the opposite: we store here the list of the bsp in the pk3 we are removing (necessary later to remove the extended resources)
        foreach($bsplist as $mapname=>$bsp) {
            if( stripos($bsp['pk3'], $filename) === FALSE) {
                $nbsplist[$mapname] = $bsp;
            } else {
                $rbsplist[] = $mapname;
            }
        }

        // Save the resulting bsplist index (without the removed bsp)
        writeIndexFile($nbsplist);

        // Delete the pk3
        @unlink($conf['storagedir'].'/'.$filename);

        // Delete all associated resources to any bsp map contained in this pk3
        //$filename_parts = pathinfo($filename);
        //$basename = $filename_parts['filename'];
        $filesList = listFiles($thumbdir);
        foreach ($filesList as $file) {
            foreach ($rbsplist as $mapname) {
                // If the file name contains the same mapname + just an extension, we remove it. Note: we must be careful, if we have mapname.txt and mapname2.txt, we don't want to remove both when we remove mapname.pk3, just mapname.txt and leave mapname2.txt
                if (preg_match("/^".preg_quote($mapname)."(\.[^.]*)$/i", $file, $match)) @unlink($thumbdir.'/'.$match[0]);
            }
        }

    // Exception handling
    } catch (Exception $e) {
        return array(-1, $e);
    }

    return 0;
}

// Helper function to automatically manage errors (printing them if necessary)
// @return  string  the error message if KO, or null (empty string) if OK
function manageError($func, $args=null) {
    if (!empty($args) and is_array($args)) {
        list($rtncode, $rtnmsg) = call_user_func_array($func, $args);
    } elseif (!empty($args)) {
        list($rtncode, $rtnmsg) = $func($args);
    } else {
        list($rtncode, $rtnmsg) = $func();
    }
    if ($rtncode < 0) return "Error: $rtnmsg"; else return null;
}

// Reformat a path to be clean and then prints it
function printPath($path) {
    // Check that the last slash is only once (not doubled)
    $path = (substr($path, -1) === '/' ? substr($path, 0, -1) : $path);

    return $path;
}

// Check a path against a directory traversal attempt by comparing a safe path (given by your code, which is sure), and a user inputted path (which may be unsafe).
// Basically, this function will compare the safepath against the userpath, and if the safepath is longer than the userpath (or more precisely: if the userpath is in a parent directory than the safepath), this means the userpath is trying to escalate and access a parent directory the user should not be able to access.
// Thank's to ircmaxell for this algorithm
function checkDirectoryTraversal($safepath, $userpath) {
    $realBase = realpath($safepath);

    $realUserPath = realpath(dirname($userpath)); // realpath does not work on inexistant files, so we check on directories (works the same for our purpose)
    if ($realUserPath === false || strpos($realUserPath, $realBase) !== 0) {
        return array(-1, "Path is not valid.");
    } else {
        return 0;
    }
}

// Print and save in a log at the same time a given message
function printAndLog($msg, $logfile=null) {
    global $conf;

    // If no $logfile is supplied...
    if (empty($logfile)) {
        // ... and no logfile is configured, then by default we save into the Apache error log
        if (empty($conf['logfile'])) {
            $logfile = 'php://stderr';
        // else if a logfile is configured, we use it
        } else {
            $logfile = $conf['logfile'];
        }
    } // else if a $logfile is specified at function call, we use it

    // Get the current time
    $timestamp = date('Y-m-d H-i-s');

    // Saving the message into the log file
    file_put_contents($logfile, "[$timestamp ".$_SERVER['REMOTE_ADDR']."] $msg"."\n", FILE_APPEND);

    // Printing the message on screen
    if (is_array($msg)) { // array, we print_r
        print('<pre>');
        print_r($msg);
        print('</pre>');
    } else { // normal string, we just print it
        print($msg);
    }

    return 0;
}

// Print a time interval in a human-readable format
// Thank's to salladin from php.net manual comments for the base function, this one is an enhanced version
function printTime($secs, $nosingleterm=false){
    // Negative values = past in time
    $positive = true;
    if ($secs < 0) {
        $positive = false;
        $secs = -$secs; // we just compute the time with the opposite (a positive value)
    }

    // Breaking seconds into parts (years, weeks, days, etc..)
    $bit = array(
        'year'        => $secs / 31556926 % 12,
        'week'        => $secs / 604800 % 52,
        'day'        => $secs / 86400 % 7,
        'hour'        => $secs / 3600 % 24,
        'minute'    => $secs / 60 % 60,
        'second'    => $secs % 60
        );

    // Fix linguistic issues
    foreach($bit as $k => $v){
        if($v > 1)$ret[] = $v . ' ' . $k . 's'; // if multiple items, append a 's'
        // If only a single item, we don't add a 's', but we can remove the '1'
        if($v == 1) {
            // We can choose to remove the ' 1 ' with this optional parameter (so that you can print something like 'this resets every day' instead of 'this resets every 1 day')
            $nosingleterm ? $ret[] = $k : $ret[] = $v . ' ' . $k;
        }
    }

    // Beautify a bit more: add comma between n-1 terms, and the last gets separated by ' and '
    if (count($ret) > 1) {
        $ret1 = array();
        $ret1[] = implode(', ', array_slice($ret, 0, count($ret)-1));
        $ret1[] = $ret[count($ret)-1];
        $ret = implode(' and ', $ret1);
    // If there's only one element in the list, we just convert it to a string
    } else {
        $ret = $ret[0];
    }

    // Negative value = past in time, so we add ' ago' at the end
    if (!$positive) $ret .= ' ago';

    // Return the final result, a human-readable time
    return $ret;
}

// Convert any tga image to anything we want
// Thank's to the anonymous poster on php.net manual! Amazing function, if you read this, kudos!
function tga2jpg ($image)
{
    $handle = fopen($image, "rb");
    $data = fread($handle, filesize($image));
    fclose($handle);

    $pointer = 18;
    $w = fileint (substr ($data, 12, 2));
    $h = fileint (substr ($data, 14, 2));
    $x = 0;
    $y = $h;

    $img = imagecreatetruecolor($w, $h);

    while ($pointer < strlen($data))
    {
        imagesetpixel ($img, $x, $y, fileint (substr ($data, $pointer, 3)));

        $x++;

        if ($x == $w)
        {
            $y--;
            $x = 0;
        }

        $pointer += 3;
    }

    for($a = 0; $a < imagecolorstotal ($img); $a++)
    {
        $color = imagecolorsforindex ($img, $a);

        $R=.299 * ($color['red'])+ .587 * ($color['green'])+ .114 * ($color['blue']);
        $G=.299 * ($color['red'])+ .587 * ($color['green'])+ .114 * ($color['blue']);
        $B=.299 * ($color['red'])+ .587 * ($color['green'])+ .114 * ($color['blue']);

        imagecolorset ($img, $a, $R, $G, $B);
    }

    imagejpeg ($img, substr($image, 0, -4).'.jpg', 100);
    imagedestroy ($img);
}

// Necessary for tga2jpg
function fileint($str)
{
    return base_convert (bin2hex (strrev ($str)), 16, 10);
}

?>