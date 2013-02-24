#!/usr/bin/env python
#
# mapsync
# Copyright (C) 2013 Larroque Stephen
#
# This program is free software; you can redistribute it and/or modify
# it under the terms of the Affero GNU General Public License as published by
# the Free Software Foundation; either version 3 of the License, or
# (at your option) any later version.

# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program; if not, write to the Free Software
# Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
#

from __future__ import print_function
import argparse
import os, sys
import pprint

# Relative path to absolute
def fullpath(relpath):
    if (type(relpath) is object or type(relpath) is file):
        relpath = relpath.name
    return os.path.abspath(os.path.expanduser(relpath))

# Check that an argument is a real directory
def is_dir(dirname):
    """Checks if a path is an actual directory"""
    if not os.path.isdir(dirname):
        msg = "{0} is not a directory".format(dirname)
        raise argparse.ArgumentTypeError(msg)
    else:
        return dirname

# Remotely download the list of pk3 on the webserver
def download_fileslist(download_url, download_password):
    import urllib2
    try:
        download_fullurl = download_url+'?password='+download_password+'&dlpk3list'
        print("Downloading list of pk3s from: "+download_fullurl)
        # Download the list
        response = urllib2.urlopen(download_fullurl, timeout=10)
        pk3list = response.read() # store the file in a list
        response.close() # close the remote file
    except Exception as err:
        print('ERROR: Could not remotely download the list of pk3 from the webserver. Error:'+str(err))

    if pk3list:
        return pk3list.rstrip("\n").split("\n")
    else:
        return None

# Get the list of files in a local folder
def local_fileslist(dir):
    return os.listdir(dir)

# Compare two (or three) lists and return the difference (elements that are only in the remotelist that are not in the others)
def compare_lists(locallist, remotelist, locallist2=None):
    if locallist2:
        return list((set(remotelist) - set(locallist)) - set(locallist2))
    else:
        return list(set(remotelist) - set(locallist))

# Given a list of files, and a url and a local storage directory, will download all maps in this directory from the given url (eg: http://domain.com/map-manager/maps/ and will download all *.pk3)
def sync(finallist, download_url, storagedir):
    import urllib
    try:
        for file in finallist:
            try: # Fail only for this file if an error occurs, and then continue onto the next file
                # Set the download url
                download_fullurl = download_url+'/'+file
                # Set the local path
                if storagedir: # set the path if a directory was specified
                    localfile = fullpath(os.path.join(storagedir, file))
                else:
                    localfile = fullpath(file)
                # Download the file
                print("\n  0%% Downloading %s" % file, end="")
                urllib.urlretrieve(download_fullurl, localfile, reporthook=dlProgress)
            except Exception as err:
                print('ERROR: an error occurred while downloading the file %s. Error: %s' % (file, err))
    except Exception as err:
        print('ERROR: Sync failed: Could not remotely download the pk3 files and sync. Error:'+str(err))

def dlProgress(count, blockSize, totalSize):
    percent = int(count*blockSize*100/totalSize)
    sys.stdout.write("\r%2d%%" % percent)
    sys.stdout.flush()

#***********************************
#                       MAIN
#***********************************

def main(argv=None):
    if argv is None:
        argv = sys.argv[1:]

    desc = '''Maps Syncer ---
    Description: Sync the local maps with a remote web server. This is to be used with the maps-manager (on lrq3000 github).
    '''

    #== Commandline arguments
    #-- Constructing the parsers
    parser = argparse.ArgumentParser(description=desc,
                                     add_help=True, argument_default=argparse.SUPPRESS, conflict_handler="resolve")
    #-- Getting the arguments
    parser.add_argument('-d', '--directory', metavar='/some/folder/ (relative or absolute path)', type=is_dir, nargs=1, required=True,
                        help='Local directory where your maps are stored')
    parser.add_argument('-b', '--directory-blacklist', metavar='/some/folder/ (relative or absolute path)', type=is_dir, nargs=1, required=False,
                        help='Another local directory to check against the remote list (maps contained in this local directory won\'t be downloaded, this is great to use when you set a different basepath and homepath)')
    parser.add_argument('-u', '--download-url', metavar='http://domain.com/map-manager/mapremote.php', type=str, nargs=1, required=True,
                        help='HTTP URL to check for the list of maps (map-manager\'s mapremote.php)')
    parser.add_argument('-w', '--download-url-maps', metavar='http://domain.com/map-manager/maps/', type=str, nargs=1, required=True, #type=argparse.FileType('rt')
                        help='URL where to download the pk3 files from')
    parser.add_argument('-p', '--password', type=str, nargs=1, required=True, #default=None but we could set default=['baseoa'] for OpenArena
                        help='Download password (of map-manager\'s mapremove.php) - either MD5 hash or clear password')

    #== Parsing the arguments
    [args, rest] = parser.parse_known_args(argv) # Storing all arguments to args

    localdir = args.directory[0]
    download_url = args.download_url[0]
    download_url_maps = args.download_url_maps[0]
    download_password = args.password[0]
    directory_blacklist = None # declaring the variable anyway, so that we get no warning from Python
    if hasattr(args, 'directory_blacklist'):
        directory_blacklist = args.directory_blacklist[0]

    # Get the remote list of pk3
    remotelist = download_fileslist(download_url, download_password)
    # Get the local list of pk3
    locallist = local_fileslist(localdir)
    locallist2 = None
    if directory_blacklist: # if we added a secondary blacklist, we also use it
        locallist2 = local_fileslist(directory_blacklist)
    # Get the difference (the list of maps we need to download)
    difflist = compare_lists(locallist, remotelist, locallist2)
    # Download the missing maps
    if difflist:
        print("Preparing to download %i maps" %len(difflist))
    sync(difflist, download_url_maps, localdir)
    # Done, printing some stats
    print("OK all done! Downloaded: %i maps" % len(difflist))
    if difflist:
        pprint.pprint(difflist)


# Calling main function if the script is directly called (not imported as a library in another program)
if __name__ == "__main__":
    sys.exit(main())
