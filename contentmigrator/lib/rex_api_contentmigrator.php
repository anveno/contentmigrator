<?php
// Aufruf via /index.php?rex-api-call=contentmigrator&image_url=
class rex_api_contentmigrator extends rex_api_function
{
    protected $published = false;        // Soll es auch im Frontend aufrufbar sein?

    function execute()
    {
        // Parameter abrufen und auswerten
        $image_name = rex_request('image_name', 'string');
        $mediapool_category = rex_request('mediapool_category', 'int');
        if (!$image_name) {
            $result = ['errorcode' => 2, 'message' => 'No parameters send'];
            self::httpError($result);
        } else {
            self::getImage($image_name, $mediapool_category);
        }
    }

    public static function getImage($image_name, $mediapool_category)
    {
        //The resource that we want to download.
        $fileUrl = $image_name;

        //The path & filename to save to.
        $targetFilename = basename($fileUrl);
        $addon = rex_addon::get('contentmigrator');
        rex_dir::create($addon->getDataPath());         // todo
        $saveTo = $addon->getDataPath($targetFilename);

        //Open file handler.
        $fp = fopen($saveTo, 'w+');

        //If $fp is FALSE, something went wrong.
        if ($fp === false) {
            $result = ['errorcode' => 3, 'message' => 'Could not open: ' . $saveTo];
            self::httpError($result);
        }

        //Create a cURL handle.
        $ch = curl_init($fileUrl);

        //Pass our file handle to cURL.
        curl_setopt($ch, CURLOPT_FILE, $fp);

        //Timeout if the file doesn't download after 20 seconds.
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);

        // some other curl options
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        //Execute the request.
        curl_exec($ch);

        //If there was an error, throw an Exception
        if (curl_errno($ch)) {
            $result = ['errorcode' => 4, 'message' => 'curl_error: ' . curl_error($ch)];
            self::httpError($result);
        }

        //Get the HTTP status code.
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        //Close the cURL handler.
        curl_close($ch);

        //Close the file handler.
        fclose($fp);

        $output = '';
        if ($statusCode == 200) {
            $output .= '<br>Downloaded: ' . $fileUrl . ' to ' . $saveTo;
        } else {
            $result = ['errorcode' => 5, 'message' => 'Status Code: ' . $statusCode];
            self::httpError($result);
        }

        // Mediapool Handling Start (thx to TobiasKrais/d2u_jobs)
        $mediapool_filename = self::getMediapoolFilename($targetFilename);
        $new_image = rex_media::get($mediapool_filename);

        // Sofern es die Mediapool Kategorie gibt, benutzen, andernfalls in den Root packen.
        $target_mediapool_category = 0;
        if ($media_cat = rex_media_category::get($mediapool_category)) {
            $target_mediapool_category = $mediapool_category;
            $output .= '<br>Bilder werden importiert nach: '.$media_cat->getName();
        }

        if ($new_image instanceof rex_media && $new_image->fileExists()) {
            // File already imported
            $output .= '<br>Image: ' . $mediapool_filename . ' already exists in mediapool';
        } else {
            // File exists only in database, but no more physically: remove it before import
            if ($new_image instanceof rex_media) {
                try {
                    rex_mediapool_deleteMedia($new_image->getFileName());
                } catch (Exception $e) {
                }
            }
            // Import
            $target_picture = rex_path::media($targetFilename);
            // Copy/Rename first (Rename = verschieben)
            if (rename($saveTo, $target_picture)) {
                chmod($target_picture, octdec(664));
                $username = rex::getUser() ? rex::getUser()->getLogin() : "api_contentmigrator_import";
                $sync_result = rex_mediapool_syncFile($targetFilename, $target_mediapool_category, '', null, null, $username);
                $final_filename = $sync_result['filename'];
                $output .= '<br>Image: ' . $final_filename . ' moved to ' . $target_picture;
            }
        }
        // Mediapool Handling Ende

        if ($output == '') {
            $result = ['errorcode' => 7, 'message' => 'Output is empty'];
            self::httpError($result);
        } else {
            self::sendSuccessfullResult($output);
        }

    }

    public static function getMediapoolFilename($old_filename)
    {
        $query = "SELECT filename FROM `" . rex::getTablePrefix() . "media` "
            . "WHERE originalname = '" . $old_filename . "'";
        $result = rex_sql::factory();
        $result->setQuery($query);
        if ($result->getRows() > 0) {
            return $result->getValue("filename");
        }

        return "";
    }

    public static function httpError($result)
    {
        header('HTTP/1.1 500 Internal Server Error');
        header('Content-Type: application/json; charset=UTF-8');
        exit(json_encode($result));
    }

    public static function sendSuccessfullResult($output)
    {
        //header('Access-Control-Allow-Origin: ' . $expected_referer);
        header('Content-Type: text/html; charset=UTF-8');
        //header('Content-Type: application/json; charset=UTF-8');
        //exit(json_encode($result));
        echo 'Status: ' . $output . '<br>';
        exit;
    }

}
