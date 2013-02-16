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
 * \description     Client form using AJAX to upload one or several maps
 */

session_start();
include_once('config.php');
include_once('lib.php');
?>
<html>
    <head>
        <!-- Load Queue widget CSS and jQuery -->
        <style type="text/css">@import url(lib/plupload/js/jquery.plupload.queue/css/jquery.plupload.queue.css);</style>
        <script type="text/javascript" src="lib/jquery.min.js"></script>

        <!-- Load plupload and all it's runtimes and finally the jQuery queue widget -->
        <script type="text/javascript" src="lib/plupload/js/plupload.full.js"></script>
        <script type="text/javascript" src="lib/plupload/js/jquery.plupload.queue/jquery.plupload.queue.js"></script>

        <script type="text/javascript">
        // Convert divs to queue widgets when the DOM is ready
        $(function() {

                <?php if ($conf['debug']) { ?>
                // Log function for the AJAX uploader
                function log() {
                        var str = "";

                        plupload.each(arguments, function(arg) {
                                var row = "";

                                if (typeof(arg) != "string") {
                                        plupload.each(arg, function(value, key) {
                                                // Convert items in File objects to human readable form
                                                if (arg instanceof plupload.File) {
                                                        // Convert status to human readable
                                                        switch (value) {
                                                                case plupload.QUEUED:
                                                                        value = 'QUEUED';
                                                                        break;

                                                                case plupload.UPLOADING:
                                                                        value = 'UPLOADING';
                                                                        break;

                                                                case plupload.FAILED:
                                                                        value = 'FAILED';
                                                                        break;

                                                                case plupload.DONE:
                                                                        value = 'DONE';
                                                                        break;
                                                        }
                                                }

                                                if (typeof(value) != "function") {
                                                        row += (row ? ', ' : '') + key + '=' + value;
                                                }
                                        });

                                        str += row + " ";
                                } else {
                                        str += arg + " ";
                                }
                        });

                        $('#log').append(str + "\n");
                }
                <?php } ?>

                // Setup the uploader form (works with html5, flash or html4)
                $("#html4_uploader").pluploadQueue({
                        // General settings
                        runtimes : 'html5, flash, html4', // fallback to html4 if everything else fail
                        url : 'mapuploader.php',
                        chunk_size : '1mb',
                        unique_names : false, // if enabled, we get a totally random name for the file and we can't find the original name

                        // Various other settings
                        max_file_size: '<?php print $conf['maxfilebytes'];?>b',
                        drop_element: '#html4_uploader',
                        multiple_queues: true,
                        filters : [
                            {title : "PK3 files (*.pk3)", extensions : "pk3"},
                        ],

                        <?php if ($conf['debug']) { ?>
                        // Logging
                        // PreInit events, bound before any internal events
                        preinit : {
                                Init: function(up, info) {
                                        log('[Init]', 'Info:', info, 'Features:', up.features);
                                },

                                UploadFile: function(up, file) {
                                        log('[UploadFile]', file);

                                        // You can override settings before the file is uploaded
                                        // up.settings.url = 'upload.php?id=' + file.id;
                                        // up.settings.multipart_params = {param1 : 'value1', param2 : 'value2'};
                                }
                        },
                        <?php } ?>

                        // Post init events, bound after the internal events
                        init : {
                                FileUploaded: function(up, file, info) {
                                        // Called when a file has finished uploading
                                        <?php if ($conf['debug']) { ?>
                                        // Log only if we are in debug mode
                                        log('[FileUploaded] File:', file, "Info:", info);
                                        <?php } ?>

                                        // JSON response decoding UNUSED
                                        //alert(info.response.indexOf("error")); // show the error code in a popup message box - always -1 in our case, because we don't return a JSON string! So not very useful...
                                        //var resp = $.parseJSON(response); // use the JQuerry parseJSON
                                        //alert(info.response.split("|")[1]); // alternatively, we can simply encode the response by concatenating with "|" instead of encoding in JSON

                                        // == Show the result of the upload: did it work (OK message) or was there an error (Error message)
                                        // Print/append the result inside the result_log div
                                        var div = document.getElementById('result_log');
                                        div.innerHTML = div.innerHTML + "<br />\n" + info['response']; // JSON.stringify(info); // to debug the result
                                        // If this was an error message (we check if we can find an 'error' string in the response), ...
                                        if (info['response'].toLowerCase().indexOf('error') >= 0) {
                                            // ... then we change the status for this file to FAILED in the plupload form (the error is already printed i the result_log div, but if we don't act on the plupload form, it will still show a green OK graphic)
                                            file.status = plupload.FAILED;
                                        }
                                        // Update the progress for this file: this allows to remove the failed uploads from the count (without it, if you upload 3 files and 2 of them fail, you will still get "Uploaded 3/3 files", when we'd rather have "Uploaded 1/3 files")
                                        up.trigger('UploadProgress', file);
                                },

                        <?php if ($conf['debug']) { ?>
                                Refresh: function(up) {
                                        // Called when upload shim is moved
                                        log('[Refresh]');
                                },

                                StateChanged: function(up) {
                                        // Called when the state of the queue is changed
                                        log('[StateChanged]', up.state == plupload.STARTED ? "STARTED" : "STOPPED");
                                },

                                QueueChanged: function(up) {
                                        // Called when the files in queue are changed by adding/removing files
                                        log('[QueueChanged]');
                                },

                                UploadProgress: function(up, file) {
                                        // Called while a file is being uploaded
                                        log('[UploadProgress]', 'File:', file, "Total:", up.total);
                                },

                                FilesAdded: function(up, files) {
                                        // Callced when files are added to queue
                                        log('[FilesAdded]');

                                        plupload.each(files, function(file) {
                                                log('  File:', file);
                                        });
                                },

                                FilesRemoved: function(up, files) {
                                        // Called when files where removed from queue
                                        log('[FilesRemoved]');

                                        plupload.each(files, function(file) {
                                                log('  File:', file);
                                        });
                                },

                                ChunkUploaded: function(up, file, info) {
                                        // Called when a file chunk has finished uploading
                                        log('[ChunkUploaded] File:', file, "Info:", info);
                                },

                                Error: function(up, args) {
                                        // Called when a error has occured
                                        log('[error] ', args);
                                }
                        <?php } ?>
                        },

                });
        });
        </script>
    </head>
    <body>

        <h1>Map uploader</h1>

        <?php
            if ($conf['captcha'] and (!isset($_SESSION['captchaok']) || !$_SESSION['captchaok'])) {
                if (empty($_POST)) {
                    echo '<br />Before being able to upload your maps, please verify that you\'re human by solving the following CAPTCHA:';
                    require_once('recaptchalib.php');
                    $publickey = $conf['recaptcha_public_key'];
                    echo '<form method="post" action="?" enctype="x-www-form-urlencoded">';
                    echo recaptcha_get_html($publickey);
                    echo '</form>';
                } else {
                    require_once('recaptchalib.php');
                    $privatekey = $conf['recaptcha_private_key'];
                    $resp = recaptcha_check_answer ($privatekey,
                                                  $_SERVER["REMOTE_ADDR"],
                                                  $_POST["recaptcha_challenge_field"],
                                                  $_POST["recaptcha_response_field"]);
                    if (!$resp->is_valid) { // Captcha is invalid
                            // What happens when the CAPTCHA was entered incorrectly
                            die("<br />The reCAPTCHA wasn't entered correctly. <a href=\"?\">Please go back and try again.</a> (reCAPTCHA said: " . $resp->error . ")");
                    } else {
                        $_SESSION['captchaok'] = true;
                        print '<a href="?">The captcha is valid! Click here to access the uploading form</a>';
                    }
                }
            } elseif (!$conf['captcha'] or (isset($_SESSION['captchaok']) && $_SESSION['captchaok']) ) {
        ?>
            <p>You can use this form to upload your maps (format .pk3) to the server.</p>
            <?php if ($conf['maxfilebytes'] > 0) {
            ?>
                <p>Note: files are limited to a maximum size of <?php print floor($conf['maxfilebytes'] / 1024); ?> KBytes per file.</p>
            <?php
            }
            if ($conf['maxnbuploads'] > 0) {
            ?>
                <p>Note2: the number of uploads is limited to <?php print $conf['maxnbuploads']; ?> files every <?php print printTime($conf['maxuploadsinterval'], true); ?>.</p>
            <?php
            }
            ?>
            <div id="html4_uploader">You browser doesn't support simple upload forms. Are you using Lynx?</div>
            <div id="result_log">Result:</div>
            <hr />

            <?php
            if ($conf['debug']) {
            ?>
                <div id="log"></div>
            <?php
            }
        }
        ?>

    <a href="http://www.gnu.org/licenses/agpl.html" target="_blank"><img src="agplv3.png" alt="This application is licensed under Affero GPL v3+" title="This application is licensed under Affero GPL v3+" /></a>

    </body>
</html>