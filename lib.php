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
            if ( !(strpos($filename, $bword) === FALSE) ) return array(-1, "This filename ($filename) is not allowed");
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
        if ( !strcmp($filename, $file) ) return array(-1, "File $filename is a duplicate, please use another name.");
    }

    // == Check bsp duplicates
    $bsplist = listBsp($fullpath);
    $indexlist = readIndexFile();

    // No BSP (no map)? Then error
    if (empty($bsplist)) return array(-1, "No map (bsp) found in your pk3.");

    $valid = true;
    // We compare each bsp in the new uploaded pk3, against every bsp we have in all our pk3 so far.
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
    if (!(file_exists($filename)) ) return array(-1, "File $filename not found.");

    // == Open the zip file
    $archive = new PclZip($filename);

    // == Read the list of compressed files in the zip if the list is not already supplied
    if (!isset($ziplist)) {
        $ziplist = $archive->listContent();
    }

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

    // == Get the list of BSP maps if not given
    if (!isset($bsplist)) {
        $bsplist = listBsp($filename, $ziplist);
    }
    $indexlist = readIndexFile();

    // == Find the Levelshot (map thumbnail) and extended informations
    //$filename_parts = pathinfo($filename); // remove the file extension (stored inside 'filename' index)
    foreach ($ziplist as $cfile) {
        foreach ($bsplist as $mapname=>$additionalinfos) {
            // Extract levelshot (map thumbnail)
            if ( preg_match('/^\/?levelshots\/'.$mapname.'\.jpg$/i', $cfile['filename']) === 1 ) {
                $rtncode = $archive->extractByIndex($cfile['index'], $conf['thumbdir'], 'levelshots');
                if ($rtncode === 0) return array(-1, 'Couldn\'t extract the map\'s levelshot thumbnail: '.$cfile['filename']);
            // Extract .txt extended description file (readme file)
            } elseif ( preg_match('/'.$mapname.'\.txt/i', $cfile['filename']) === 1 // either we look for a mapname.txt description file...
                      or preg_match('/^README(.txt)?$/i', $cfile['filename']) === 1) { // ...or either a README at the root
                $rtncode = $archive->extractByIndex($cfile['index'], $conf['thumbdir']);
                if ($rtncode === 0) return array(-1, 'Couldn\'t extract the description file: '.$cfile['filename']);
                // if the file is named README, we rename it to the same filename as the bsp
                if (preg_match('/^README(.txt)?$/i', $cfile['filename'], $match) === 1) {
                    @rename($conf['thumbdir'].'/'.$match[0], $filename_parts['filename'].'.txt');
                }
            // Extract .arena short description file
            } elseif ( preg_match('/^(.*)'.$mapname.'\.arena/i', $cfile['filename'], $match) === 1 ) {
                $rtncode = $archive->extractByIndex($cfile['index'], $conf['thumbdir'], $match[1]); // we match and remove everything in the path prior to the file (ie: any parent folder, so that we just extract this file only)
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

    if (empty($dir)) $dir = $conf['storagedir'];

    try {
        rebuildIndexFile($dir);
        $filesList = listFiles($dir);
        foreach ($filesList as $file) {
            extractThumbnailAndInfos($dir.'/'.$file);
        }
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

function removePk3($filename, $ziplist=null) {
    global $conf;

    if (!(file_exists($conf['storagedir'].'/'.$filename)) ) return array(-1, "File $filename not found.");

    try {
        $bsplist = readIndexFile();

        $nbsplist = array();
        foreach($bsplist as $mapname=>$bsp) {
            if(strpos($bsp['pk3'], $filename) === FALSE) {
                $nbsplist[$mapname] = $bsp;
            }
        }

        writeIndexFile($nbsplist);

        @unlink($conf['storagedir'].'/'.$filename);
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

    // Saving the message into the log file
    file_put_contents($logfile, $msg."\n", FILE_APPEND);

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

?>