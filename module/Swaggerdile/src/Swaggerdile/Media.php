<?php
/*
 * Media.php
 *
 * Class that handles various media related features, such as path
 * handling and secure media handling.
 */

namespace Swaggerdile;


class Media
{
    /*
     * Where do public files go that don't require protection?
     *
     * @var string
     */
    static protected $_publicFileBasePath = '';

    /*
     * Where do files go that DO require protection?
     *
     * @var string
     */
    static protected $_privateFileBasePath = '';

    /*
     * Initialize the media manager.
     *
     *
     * @param string Public file path
     * @param string Private file path
     */
    public function __construct($publicPath, $privatePath)
    {
        self::setPublicFileBasePath($publicPath);
        self::setPrivateFileBasePath($privatePath);
    }

    /*
     *
     * Statics to set/get the media file paths.
     *
     */

    static public function setPublicFileBasePath($path)
    {
        self::$_publicFileBasePath = $path;
    }

    static public function setPrivateFileBasePath($path)
    {
        self::$_privateFileBasePath = $path;
    }

    static public function getPublicFileBasePath()
    {
        return self::$_publicFileBasePath;
    }

    static public function getPrivateFileBasePath()
    {
        return self::$_privateFileBasePath;
    }

    /*
     * Get the icon for a profile page.  Returns an image URL
     * or 'false' if no image available.
     *
     * @param Profiles|profile ID
     * @return string|false
     */
    static public function getProfileIcon($profile)
    {
        $profileId = $profile;

        if(is_object($profile)) {
            $profileId = $profile->getId();
        }

        $path = self::getPublicFileBasePath() . "/profile-{$profileId}-icon.jpg";

        clearstatcache();
        if(is_file($path)) {
            // return a URL path.
            return substr($path, strpos($path, '/public/')+7);
        }

        return false;
    }

    /*
     * Get the VIP to use for a stream page, or 'false' if no
     * VIP image available.
     *
     * @param Profiles|profile ID
     * @return String|false
     */
    static public function getStreamVIP($profile)
    {
        return self::getPublicImageType($profile, 'vip');

        return false;
    }

    /*
     * Get the background image URL to use for a stream page, or 'false' if no
     * background image available.
     *
     * @param Profiles|profile ID
     * @return String|false
     */
    static public function getStreamBackground($profile)
    {
        return self::getPublicImageType($profile, 'stream-background');

        return false;
    }

    /*
     * Get the background image URL to use for a stream's offline image,
     * or 'false' if no background image available.
     *
     * @param Profiles|profile ID
     * @return String|false
     */
    static public function getStreamOfflineBackground($profile)
    {
        return self::getPublicImageType($profile, 'stream-offline');
    }

    /*
     * Get the donation button image, if available, or 'false' if no background
     * image available.
     *
     * @param Profiles|profile ID
     * @return String|false
     */
    static public function getStreamDonateButton($profile)
    {
        return self::getPublicImageType($profile, 'donate');
    }

    /*
     * Get a public image of a given type name
     *
     * @param Profiles|profile ID
     * @param string type, used as suffix
     * @return String|false
     */
    static public function getPublicImageType($profile, $type)
    {
        $profileId = $profile;

        if(is_object($profile)) {
            $profileId = $profile->getId();
        }

        $path = self::getPublicFileBasePath() . "/profile-{$profileId}-{$type}";

        clearstatcache();
        if(is_file($path)) {
            // return a URL path.
            return substr($path, strpos($path, '/public/')+7);
        }

        return false;
    }

    /*
     * Delete a public image of a given type name
     *
     * @param Profiles|profile ID
     * @param string type, used as suffix
     */
    static public function deletePublicImageType($profile, $type)
    {
        $profileId = $profile;

        if(is_object($profile)) {
            $profileId = $profile->getId();
        }

        // Security
        $type = preg_replace('/[^\d\w-]/', '', $type);

        $path = self::getPublicFileBasePath() . "/profile-{$profileId}-{$type}";

        if(is_file($path)) {
            // Delete it
            unlink($path);
        }
    }


    /*
     * Generate a thumbnail filesystem path
     *
     * @param Content
     * @return string
     */
    static public function contentThumbnailPath($content)
    {
        return self::getPrivateFileBasePath() . "/{$content->getProfileId()}/thumbnails/{$content->getId()}";
    }

    /*
     * Generate a filesystem path
     *
     * @param Content
     * @return string
     */
    static public function contentPath($content)
    {
        return self::getPrivateFileBasePath() . "/{$content->getProfileId()}/{$content->getId()}";
    }

    /*
     * Does a piece of content have a thumbnail?  Check!
     *
     * @param Content
     * @return boolean
     */
    static public function contentHasThumbnail($content)
    {
        return is_file(self::contentThumbnailPath($content));
    }

    /*
     * Does a piece of content have a file?  Check!
     *
     * @param Content
     * @return boolean
     */
    static public function contentHasFile($content)
    {
        return is_file(self::contentPath($content));
    }

    /*
     * Deletes on-disk files for a given piece of content.
     *
     * @param Content
     */
    static public function deleteContent($content)
    {
        $contentPath = self::contentPath($content);
        $contentThumbPath = self::contentThumbnailPath($content);

        if(is_file($contentPath)) {
            unlink($contentPath);
        }

        if(is_file($contentThumbPath)) {
            unlink($contentThumbPath);
        }
    }

    /*
     * Resize an image in the cleanest way possible.  Designed to scale
     * down and not up (though it might work with up?  ... why would I want
     * to scale up?  Future me can answer this question if it comes up).
     *
     * @param resource originalFile
     * @param integer target width
     * @param integer target height
     * @param boolean letterbox ?
     * @return resource newFile
     *
     * Will always be successsful -- throws exception if something
     * crazy happens.
     *
     * Got code from here:
     * http://spotlesswebdesign.com/blog.php?id=1
     *
     * You should imagedestroy both the original file and the new file when
     * you are done with them.
     *
     **********************************************************************
     *
     * Typical call pattern (so I remember) :
     *
     * // Let's load!
     * $source = imagecreatefromstring(file_get_contents($filename));
     *
     * // Fail ?
     * if($source === FALSE) {
     *     throw new \Exception("Could not load the image provided.  Please try again!");
     * }
     *
     * $destImage = self::scaleImage($source, 280, 200, true);
     *
     * // save it
     * if(!imagejpeg($destImage, $targetFileName, 90)) {
     *     imagedestroy($destImage);
     *     imagedestroy($source);
     *     throw new \Exception("Could not save the image we have generated.  Please try again later, or report this error to support.");
     * }
     *
     * imagedestroy($destImage);
     * imagedestroy($source);
     */
    static public function scaleImage($source_image,
                                      $destination_width, $destination_height,
                                      $letterbox = false) {
        // Basic calculations used everywhere
        $source_width = imagesx($source_image);
        $source_height = imagesy($source_image);
        $source_ratio = $source_width / $source_height;
        $destination_ratio = $destination_width / $destination_height;

        // Default values
        $temp_height = $destination_height;
        $temp_width = $destination_width;
        $destination_x = $destination_y = 0;

    /** CROP TO FIT
     **
     ** I don't think I like this option.
     ** But I'll keep the code in case I want it later.
        if ($letterbox === false) {
            // crop to fit
            if ($source_ratio > $destination_ratio) {
                // source has a wider ratio
                $temp_width = (int)($source_height * $destination_ratio);
                $temp_height = $source_height;
                $source_x = (int)(($source_width - $temp_width) / 2);
                $source_y = 0;
            } else {
                // source has a taller ratio
                $temp_width = $source_width;
                $temp_height = (int)($source_width / $destination_ratio);
                $source_x = 0;
                $source_y = (int)(($source_height - $temp_height) / 2);
            }

            $destination_x = 0;
            $destination_y = 0;
            $source_width = $temp_width;
            $source_height = $temp_height;
            $new_destination_width = $destination_width;
            $new_destination_height = $destination_height;
        } else {
     **/
            // letterbox
            if ($source_ratio < $destination_ratio) {
                // source has a taller ratio
                $temp_width = (int)($destination_height * $source_ratio);
                $temp_height = $destination_height;

                if($letterbox) {
                    $destination_x = (int)(($destination_width - $temp_width) / 2);
                }

                $destination_y = 0;
            } else {
                // source has a wider ratio
                $temp_width = $destination_width;
                $temp_height = (int)($destination_width / $source_ratio);

                $destination_x = 0;

                if($letterbox) {
                    $destination_y = (int)(($destination_height - $temp_height) / 2);
                }
            }

            $source_x = 0;
            $source_y = 0;
            $new_destination_width = $temp_width;
            $new_destination_height = $temp_height;
    /**
        }
     **/
        if ($letterbox) {
            $destination_image = imagecreatetruecolor($destination_width, $destination_height);
            imagefill($destination_image, 0, 0, imagecolorallocate ($destination_image, 0, 0, 0));
        } else {
            $destination_image = imagecreatetruecolor($new_destination_width, $new_destination_height);
        }

        imagecopyresampled($destination_image, $source_image, $destination_x, $destination_y, $source_x, $source_y, $new_destination_width, $new_destination_height, $source_width, $source_height);
   
        return $destination_image;
    } 

    /*
     * Store an arbitrary public image -- these are used for the
     * stuff like stream background pictures, paypal icons, etc.
     * which are all images that can be any size and are not scaled
     * or type-restricted.
     *
     * @param Profiles|Profile Id
     * @param string Filename
     * @param string type
     *
     * 'Type' will be used as a suffix for the image.
     */
    static public function storePublicImage($profile, $filename, $type)
    {
        // This is a super fatal error
        if(!strlen(($destPath = self::getPublicFileBasePath())) ||
           !is_dir($destPath)) {
            throw new \Exception("There is a problem with Swaggerdile's image upload directory that prevents us from accepting new files.  Please report this problem to Support.");
        }

        $profileId = $profile;

        if(is_object($profile)) {
            $profileId = $profile->getId();
        }

        // Target file name
        $targetFileName = self::getPublicFileBasePath() . "/profile-{$profileId}-{$type}";

        // Check its an image
        $fileInfo = getimagesize($filename);

        // Make sure it's valid
        if($fileInfo === FALSE) {
            throw new \Exception("Could not load the image provided.  Please try again!");
        }

        // Move it!
        if(!move_uploaded_file($filename, $targetFileName)) {
            throw new \Exception("Could not save the image you uploaded.  Please try again later, or report this error to support.");
        }
    }

    /*
     * Store a profile icon for a profile page.  Provide the file
     * on the file system (usually tmp_name from the FILES).  The original
     * name doesn't matter as it will be stripped anyway.
     *
     * We will convert the image to 280x280 if we need to, and JPEG.
     *
     * @param Profiles|Profile Id
     * @param string filename
     *
     * Throws exception on failure with message explaining.
     */
    static public function storeProfileIcon($profile, $filename)
    {
        // This is a super fatal error
        if(!strlen(($destPath = self::getPublicFileBasePath())) ||
           !is_dir($destPath)) {
            throw new \Exception("There is a problem with Swaggerdile's image upload directory that prevents us from accepting new files.  Please report this problem to Support.");
        }

        $profileId = $profile;

        if(is_object($profile)) {
            $profileId = $profile->getId();
        }

        // Target file name
        $targetFileName = self::getPublicFileBasePath() . "/profile-{$profileId}-icon.jpg";

        /*
         * Verify :
         *
         * 1. File is JPEG
         * 2. File size is 280 x 200
         *
         * Fix if needed.
         */
        $fileInfo = getimagesize($filename);

        // Make sure it's valid
        if($fileInfo === FALSE) {
            throw new \Exception("Could not load the image provided.  Please try again!");
        }

        // Make sure the dimensions work -- fix if not
        if(($fileInfo[0] != 280) ||
           ($fileInfo[1] != 200) ||
           (($fileInfo[2] != IMAGETYPE_JPEG) && ($fileInfo[2] != IMAGETYPE_JPEG2000))) {

            // Let's load!
            $source = imagecreatefromstring(file_get_contents($filename));

            // Fail ?
            if($source === FALSE) {
                throw new \Exception("Could not load the image provided.  Please try again!");
            }

            $destImage = self::scaleImage($source, 280, 200, true);

            // save it
            if(!imagejpeg($destImage, $targetFileName, 90)) {
                imagedestroy($destImage);
                imagedestroy($source);
                throw new \Exception("Could not save the image we have generated.  Please try again later, or report this error to support.");
            }

            imagedestroy($destImage);
            imagedestroy($source);
        } else {
            if(!move_uploaded_file($filename, $targetFileName)) {
                throw new \Exception("Could not save the image you uploaded.  Please try again later, or report this error to support.");
            }
        }
    }

    /*
     * Allowed page image types
     *
     * This is a list of allowed mime types for images that are embeded in
     * profiles.
     *
     * @return array of strings
     */
    static public function getValidImageTypes()
    {
        return array('image/gif', 'image/png', 'image/jpeg', 'image/pjpeg');
    }

    /*
     * Get our mime type from file
     *
     * @param string filename
     * @return string mime type
     */
    static public function getMimeTypeFromFile($filename)
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        return $finfo->file($filename);
    }

    /*
     * Get our mime type from string
     *
     * @param string data
     * @return string mime type
     */
    static public function getMimeTypeFromString($data)
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        return $finfo->buffer($data);
    }

    /*
     * Filter HTML block for inline image content
     *
     * The HTML editors like to store the image content inline, even though
     * I have asked them not to.  As the editor I'm currently using may get
     * switched out later, for now I will make a parser rather than trying to
     * fix the editor.
     *
     * This will take an HTML string and replace all instances of inline images
     * with proper links, returning the corrected string.  It will raise an
     * exception if what is stored is not a proper string or is an image that
     * is too large.
     *
     * @param Profile
     * @param string
     * @param integer contentID default false
     * @return string
     */
    static public function filterInlineData($profile, $html, $contentId=false)
    {
        $profileDir = '';

        if($contentId === false) {
            // This is a super fatal error
            if(!strlen(($destPath = self::getPublicFileBasePath())) ||
               !is_dir($destPath)) {
                throw new \Exception("There is a problem with Swaggerdile's image upload directory that prevents us from accepting new files.  Please report this problem to Support.");
            }

            // Make a directory for our profile
            $profileDir = $destPath . '/' . $profile->getId();

            if(!is_dir($profileDir)) {
                if(!mkdir($profileDir)) {
                    throw new \Exception("Could not create upload directory for your profile - please report this error to Swaggerdile support.  This will not go away on its own.");
                }
            }
        } else {
            // This is a super fatal error
            if(!strlen(($destPath = self::getPrivateFileBasePath())) ||
               !is_dir($destPath)) {
                throw new \Exception("There is a problem with Swaggerdile's image upload directory that prevents us from accepting new files.  Please report this problem to Support.");
            }

            // Make a directory for our profile, make sure all pieces
            // are there.
            $profileDir = $destPath . '/' . $profile->getId();

            if(!is_dir($profileDir)) {
                if(!mkdir($profileDir)) {
                    throw new \Exception("Could not create upload directory for your profile - please report this error to Swaggerdile support.  This will not go away on its own.");
                }
            }

            $profileDir .= '/dump';

            if(!is_dir($profileDir)) {
                if(!mkdir($profileDir)) {
                    throw new \Exception("Could not create upload directory for your profile - please report this error to Swaggerdile support.  This will not go away on its own.");
                }
            }
        }

        if(!preg_match_all('/<img .*src=["\']data:(.*),([^"\']+)["\']/Uis', $html, $matches,  PREG_SET_ORDER)) {
            return $html; // nothing to do
        }

        $validImageTypes = self::getValidImageTypes();

        foreach($matches as $matchSet) {
            // The type better be correct
            $info = explode(';', $matchSet[1]);
            $content_type = '';
            $replace_url = false;

            if(count($info) > 1) {
                $content_type = $info[0];

                if($info[count($info)-1] != 'base64') {
                    // Not binary
                    $replace_url = '';
                }
            } else {
                $replace_url = '';
            }

            // Validate content type
            if(!in_array($content_type, $validImageTypes)) {
                $replace_url = '';
            }

            // See if we've survived long enough to store the image
            if($replace_url === false) {
                // Decode our data
                $rawData = base64_decode($matchSet[2]);

                // Verify mime type
                if(self::getMimeTypeFromString($rawData) != $content_type) {
                    $replace_url = '';
                } else {
                    // Make a hash of it -- this will be our filename
                    $hash = hash('sha256', $rawData, false);

                    // Figure out extension.
                    list($_, $ext) = explode('/', $content_type);

                    $filename = '';

                    if($contentId === false) {
                        $filename = "${profileDir}/{$hash}.{$ext}";
                        $replace_url = substr($filename, strpos($filename, '/public/')+7);
                    } else {
                        $filename = "${profileDir}/{$contentId}-{$hash}.{$ext}";
                        $replace_url = "/{$profile->getUrl()}/content/{$contentId}/{$hash}.{$ext}";
                    }

                    if(!is_file($filename)) {
                        $fd = fopen($filename, "w");
                        fputs($fd, $rawData);
                        fclose($fd);
                    }
                }
            }

            $html = str_replace("data:{$matchSet[1]},{$matchSet[2]}", $replace_url, $html);            
        }

        return $html;
    }
}
