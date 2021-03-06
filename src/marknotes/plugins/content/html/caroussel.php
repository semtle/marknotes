<?php

/**
 * Add a caroussel if images in a HTML page.
 *
 * Find any '%CAROUSSEL images_folder%' and get the list of imaages in that folder, then
 * insert <img> tags.
 */

namespace MarkNotes\Plugins\Content\HTML;

defined('_MARKNOTES') or die('No direct access allowed');

class Caroussel
{
    public static function doIt(&$content = null) : bool
    {
        if (trim($content) === '') {
            return true;
        }

        $aeSession = \MarkNotes\Session::getInstance();
        $task = $aeSession->get('task', '');

        $matches = array();

        // Check if the content contains things like '%CAROUSSEL .images/folder/demo%'
        // i.e. '%CAROUSSEL ' followed by a foldername and ending by '%'

        if (preg_match_all('/%CAROUSSEL ([^\\%]*)%/', $content, $matches)) {
            $aeFiles = \MarkNotes\Files::getInstance();
            $aeFunctions = \MarkNotes\Functions::getInstance();
            $aeSettings = \MarkNotes\Settings::getInstance();
            $aeSession = \MarkNotes\Session::getInstance();

            // Retrieve the note fullpath
            $root = rtrim($aeSettings->getFolderDocs(true), DS).DS;
            $root = $root.dirname($aeSession->get('filename')).DS;

            // Retrieve the note URL
            $url = rtrim($aeFunctions->getCurrentURL(true, false), '/');
            $url .= '/'.$aeSettings->getFolderDocs(false);
            $url .= dirname($aeSession->get('filename')).DS;
            $url = str_replace(DS, '/', $url);

            $arrTags = $matches[0];
            $arrFolders = $matches[1];

            $i = 0;
            $j = count($arrFolders);

            for ($i == 0; $i < $j; $i++) {

                // Retrieve the full folder name like
                // C:\sites\notes\docs\folder\subfolder\.images
                $folder = str_replace('/', DS, $arrFolders[$i]);
                if (!(is_dir($folder))) {
                    $folder = $root.$folder.DS;
                }

                if (!(is_dir($folder))) {
                    // Correctly handle accentuated characters
                    $folder = utf8_decode($folder);
                }

                if (is_dir($folder)) {
                    $arrFiles = $aeFiles->rglob('*', $folder);

                    $images = '';
                    foreach ($arrFiles as $file) {
                        $ext = strtolower($aeFiles->getExtension($file));

                        if (in_array($ext, array('gif','jpg','jpeg','png','svg','webp'))) {
                            $file = utf8_encode($file);
                            $file = str_replace($root, '', $file);

                            $file = str_replace(DS, '/', $file);

                            $img = '<img src="'.$url.$file.'" alt="'.basename($file).'"/>';
                            $images .= '<hr/>'.$img;
                        }
                    }
                    $content = str_replace($arrTags[$i], $images, $content);
                }
            }
        }
        return true;
    }

    /**
     * Attach the function and responds to events
     */
    public function bind() : bool
    {
        $aeSession = \MarkNotes\Session::getInstance();
        $task = $aeSession->get('task', '');

        // This plugin is only needed when the task is one of the following

        if (!in_array($task, array('html','reveal'))) {
            return true;
        }

        $aeEvents = \MarkNotes\Events::getInstance();
        $aeEvents->bind('render.content', __CLASS__.'::doIt');
        return true;
    }
}
