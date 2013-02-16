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
 * \description     Admin and download password hash generator
 */

include_once(dirname(__FILE__).'/lib-crypt.php');
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <title>Quick MD5 generator</title>
    </head>
    <body>
        <h1>Quick MD5 generator</h1>
        <div>Never trust an online MD5 generator, they may keep it in their rainbow tables ! See md5rainbow.com</div>
        <form action="?" method="get">
            <input type="text" name="password"/>
            <input type="submit" value="Generate"/>
        </form>
        <?php
        if (!empty($_GET['password'])) {
            echo '<div>Password: '.$_GET['password'].'<br />
                                MD5: '.md5($_GET['password']).'
                                </div>';
            $hash = obscure($_GET['password']);
            echo '<div>Obscure: '.$hash.'</div>';
            //echo '<div>Obscure: '.obscure($_GET['password'], $hash).'</div>';
        }
        ?>
    <br /><br />
    <a href="http://www.gnu.org/licenses/agpl.html" target="_blank"><img src="agplv3.png" alt="This application is licensed under Affero GPL v3" title="This application is licensed under Affero GPL v3" /></a>
    </body>
</html>
