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
 * \description     Dynamically list the maps and show extended informations if asked
 */

session_start();
require_once('config.php');
require_once('lib.php');
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" dir="ltr">
<head>
<meta http-equiv="content-type" content="text/html; charset=UTF-8"/>
<title>Maps listing</title>
<style type="text/css">@import url(style/mapmanager.css);</style>
</head>
<body>

<h1>Maps listing</h1>

<?php
// == Maps listing mode
if (!isset($_GET['map'])) {
?>

<p>You will find below the list of all the maps that are available on the server.</p>

<table cellspacing="3" id="maplister_table">
	<tr>
		<th>Levelshot</th>
		<th>Mapname</th>
                <th>Contained in pk3</th>
                <?php if ($_SESSION['auth']) echo '<th>Admin commands</th>'; ?>
	</tr>
	<?php
        // Loading the list of bsp from index
        $bsplist = readIndexFile();

        // Preparing image redimensionning if specified
        $imgwidth = '';
        $imgheight = '';
        if ($conf['thumbwidth'] > 0) $imgwidth = 'width="'.$conf['thumbwidth'].'"';
        if ($conf['thumbheight'] > 0) $imgheight = 'height="'.$conf['thumbheight'].'"';

        $count = 1;
        foreach ($bsplist as $mapname=>$bsp) {
            $count++;
            ?>
	<tr class="<?php echo $count % 2 == 0 ? 'alt' : ''; ?>">
		<td><?php
                // Printing an image in the first cell if available
                $mapimage = $conf['thumbdir'].'/'.$mapname.'.jpg';
                if (file_exists($mapimage)) echo '<a href="'.$mapimage.'"><img src="'.$mapimage.'" alt="'.$mapname.'_levelshot" title="'.$mapname.'_levelshot" '.$imgwidth.' '.$imgheight.' /></a>';
                ?></td>
		<td><?php echo '<span class="mapname"><a href="'.$_SERVER['PHP_SELF'].'?map='.$mapname.'">'.$mapname.'</span></a>'; ?></td>
                <td><?php echo '<span class="pk3">'.$bsplist[$mapname]['pk3'].'</span></a>'; ?></td>
                <?php if ($_SESSION['auth']) echo '<td><a href="mapremote.php?remove='.$bsplist[$mapname]['pk3'].'">Delete the map/pk3</a></td>'; ?>
	</tr>
	<?php } ?>
</table>

<?php
// == Map info mode (more detailed info about one bsp)
} else {
    // Loading the list of bsp from index
    $bsplist = readIndexFile();
?>
<p><a href="?">Back to the maps listing</a></p>

<p>More details about the map <strong><?php print $_GET['map'] ?></strong>:</p>
<?php
    if (!isset($bsplist[$_GET['map']])) {
        print '<p>This map does not exist on this server!</p>';
    } else {
        $bsp = $bsplist[$_GET['map']];
?>
<p>
<?php
        $mapimage = printPath($conf['thumbdir']).'/'.$bsp['mapname'].'.jpg';
        if (file_exists($mapimage)) echo '<a href="'.$mapimage.'"><img src="'.$mapimage.'" alt="'.$bsp['mapname'].'_levelshot" title="'.$bsp['mapname'].'_levelshot" /></a>';
?>
<br />
<?php
foreach($bsp as $key=>$val) {
    if ($key === 'size') { // format a little better and more human-readable the size
        $val = round($val/1024).' Kbytes';
    } elseif ($key === 'index') { // skip the zip index, only used internally...
        continue;
    }
    print ucfirst($key).": $val <br />";
}
?>
<br />PK3 file size: <?php print round(filesize($conf['storagedir'].'/'.$bsp['pk3'])/1024); ?> Kbytes<br /><span style="font-style: italic">(nb: pk3's size may be smaller than bsp's because the pk3 is zip compressed)</span>
<br />Contained in the PK3: <?php print $bsp['pk3']; ?>
<br /><a href="<?php print printPath($conf['storagedir']).'/'.$bsp['pk3']; ?>">&gt;&gt; Download the map (click here) &lt;&lt;</a>
<br />
<hr />
<?php
        $maparena = $conf['thumbdir'].'/'.$bsp['mapname'].'.arena';
        if (file_exists($maparena)) print("Arena file description:<br />".nl2br(file_get_contents($maparena)));
?>
<hr />
<?php
        $mapdesc = $conf['thumbdir'].'/'.$bsp['mapname'].'.txt';
        if (file_exists($mapdesc)) print("Long description:<br />".nl2br(file_get_contents($mapdesc)));
?>
<hr />
<p>List of files contained in the same PK3:<br />
<?php
        $filesList = listFilesInZip($conf['storagedir'].'/'.$bsp['pk3']);
        foreach($filesList as $cfile) {
            print $cfile."\n";
        }
    }
?>
<p><a href="?">Back to the maps listing</a></p>
<?php
}
?>

</body>
</html>