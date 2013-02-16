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
 * \description     Quick admin panel (just allows to login and use features of mapremote.php without having to use the URL/HTTP API)
 */

session_start();
require_once('config.php');
require_once('lib.php');


/*******************************/
/********* FUNCTIONS ********/
/*******************************/

/***** PARAMETERS *****/


/***** PRINTING FUNCTIONS AND TEMPLATES *****/

function print_header() {
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <title>Maps Manager - Admin Panel</title>
    </head>
    <body>
<?php
}

function print_footer() {
?>
    <br /><br />
    <a href="http://www.gnu.org/licenses/agpl.html" target="_blank"><img src="agplv3.png" alt="This application is licensed under Affero GPL v3" title="This application is licensed under Affero GPL v3" /></a>
    </body>
</html>
<?php
}

function show_form($msgerror = null) {
    print_header();
?>
        <h1>Maps Manager - Admin Panel</h1>
        <div class="msgerror"><?php echo $msgerror; ?></div>
        <div>This section is restricted. Please authenticate yourselves.</div>
        <form action="?" method="post" enctype="x-www-form-urlencoded">
            Password: <input type="password" name="password"/><br />
            <input type="submit" value="Authenticate"/>
        </form>
<?php
    print_footer();
    exit();
}

function show_panel($msgerror = null) {
    include('config.php');
    print_header();
?>
        <h1>OA Clan Planner - Admin Panel</h1>
        <div><a href="?logout=true">Logout</a></div>
        <div class="msgerror"><?php echo $msgerror; ?></div>
        <p>You are now loggued, please <a href="maplister.php">click here to access the map lister and admin commands from there directly</a>.</p>
        <p>You can also <a href="mapremote.php?rebuild">rebuild the index of maps</a> or even do a <a href="mapremote.php?rebuildfull">full rebuild of the index and the associated resources (levelshots, long descriptions, arena files, etc.)</a>.</p>
        <p>You can also now <a href="mapuploaderform.php">upload an infinite number of files</a> (as long as you are below the storage limit).</p>
<?php
    print_footer();
    exit();
}




/************************/
/********* MAIN ********/
/************************/

$msgerror = '';

//== USER ALREADY AUTHENTICATED
if (isset($_SESSION['auth'])) {
    // If the session is bugged, we ask to login again
    if ($_SESSION['auth'] != 'yes') {
        show_form($msgerror);
    // Else, the session is ok and the user is really authenticated
    } else {
        // Logout if link is clicked
        if (isset($_REQUEST['logout'])) {
            session_destroy();
            show_form('You have successfully unlogged.');
        // Else, show the normal admin panel
        } else {
            show_panel();
        }
    }

//== USER NOT AUTHENTICATED (FIRST TIME)
} else {
    // If there are missing/blank fields or the form is loaded for the first time
    if (empty($_POST['password'])) {
        show_form(); // We just print out the login form
    // Else, user is trying to login
    } else {
        //---- Checking the authentication
        if (obscure($_POST["password"], $conf['adminhash']) == $conf['adminhash']) { // If admin login username and password is the same as the ones submitted in the fields
            $_SESSION['auth'] = 'yes'; // User is authenticated
            show_panel(); // We printout the panel
        // Else the login is wrong
        } else {
            $msgerror .= "Password is invalid.";
            show_form($msgerror);
        }
    }
}

?>
