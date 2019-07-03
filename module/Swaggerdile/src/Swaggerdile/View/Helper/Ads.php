<?php
/*
 * Ads.php
 *
 * Ad handling helper for loading randomized ad blocks.
 *
 * @author sconley
 */

namespace Swaggerdile\View\Helper;

use Zend\View\Helper\AbstractHelper;


class Ads extends AbstractHelper
{
    /*
     * Invoke to render an ad block.  The type string is an arbitrary
     * type which is used to select the directory to load the ad file
     * from.
     *
     * @param string ad type
     * @param optionally any classes to apply to the image
     * @param optionally any classes to apply to the a-link
     * @return HTML block
     */
    public function __invoke($ad_type, $img_classes="", $a_classes="")
    {
        $basepath = getcwd() . "/public/a/{$ad_type}";

        if(!is_dir($basepath)) {
            return "";  // we'll never become an Avatar at this rate
        }

        // TODO: This could be cached as this will infrequently change.
        // But this directory will also be pretty small so maybe
        // it doesn't matter much.
        $files = array();
        $dh = opendir($basepath);

        while(($filename = readdir($dh)) !== false) {
            // Only grab image files
            if(($filename != '.') && ($filename != '..') &&
               substr($filename, -4) != '.txt') {
                $files[] = $filename;
            }
        }

        if(!count($files)) { // none
            return "";
        }

        // Get our file
        $file = $files[array_rand($files)];

        // Get our link
        $link = trim(
                    file_get_contents(
                        $basepath . '/' .
                        substr($file, 0, strrpos($file, '.')) .
                        '.txt'
                    )
        );

        // return it
        return '<a href="' . htmlentities($link) . '" class="' .
               $a_classes . '" target="_blank"><img src="' .
               $this->view->basePath("/a/{$ad_type}/{$file}") .
               '" class="' . $img_classes . '" /></a>';
    }
}
