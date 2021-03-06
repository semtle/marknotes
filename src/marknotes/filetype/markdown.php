<?php
/* REQUIRES PHP 7.x AT LEAST */
namespace MarkNotes\FileType;

defined('_MARKNOTES') or die('No direct access allowed');

class Markdown
{
    protected static $hInstance = null;

    public function __construct()
    {
        return true;
    } // function __construct()

    public static function getInstance()
    {
        if (self::$hInstance === null) {
            self::$hInstance = new Markdown();
        }
        return self::$hInstance;
    } // function getInstance()

    /**
     * From a markdown content, return an heading text (by default the ""# TEXT" i.e. the heading 1)
     */
    public function getHeadingText(string $markdown, string $heading = '#') : string
    {
        // Try to find a heading 1 and if so use that text for the title tag of the generated page
        $matches = array();
        $title = '';

        try {
            preg_match("/".$heading." ?(.*)/", $markdown, $matches);
            $title = (count($matches) > 0) ? trim($matches[1]) : '';

            // Be sure that the heading 1 wasn't type like   # MyHeadingOne # i.e. with a final #

            $title = ltrim(rtrim($title, $heading), $heading);
        } catch (Exception $e) {
        }

        return $title;
    } //  function getHeadingText()

    /**
     * Remove <encrypt xxxx> content </encrypt> and replace by *confidential*
     */
    public function ShowConfidential(string $markdown) : string
    {

        // ([\\S\\n\\r\\s]*?)  : match any characters, included new lines
        preg_match_all('/<encrypt[[:blank:]]*[^>]*>([\\S\\n\\r\\s]*?)<\/encrypt>/', $markdown, $matches);

        // Remove the tag prefix
        $aeSettings = \MarkNotes\Settings::getInstance();
        $prefix = $aeSettings->getTagPrefix();
        $markdown = str_replace($prefix, '', $markdown);

        // If matches is greater than zero, there is at least one <encrypt> tag found in the file content
        if (count($matches[0]) > 0) {
            $j = count($matches[0]);

            $i = 0;

            for ($i; $i < $j; $i++) {
                $markdown = str_replace($matches[0][$i], '<strong class="confidential">'.$aeSettings->getText('confidential', 'confidential').'</strong>', $markdown);
            }
        }

        return $markdown;
    } // function ShowConfidential

    /**
     * Convert any links like ![alt](image/file.png) or <img src='image/file.php' /> to
     * an absolute link to the image
     */
    private function setImagesAbsolute(string $markdown, array $params = null) : string
    {
        $aeFiles = \MarkNotes\Files::getInstance();
        $aeFunctions = \MarkNotes\Functions::getInstance();
        $aeSettings = \MarkNotes\Settings::getInstance();

        $folderNote = str_replace('/', DS, rtrim($aeSettings->getFolderDocs(true), DS).'/');

        if (isset($params['filename'])) {
            $folderNote .= rtrim(dirname($params['filename']), DS).DS;

            // Get the full path to this note
            $url = rtrim($aeFunctions->getCurrentURL(false, false), '/').'/'.rtrim($aeSettings->getFolderDocs(false), DS).'/';
            $folder = $url.str_replace(DS, '/', dirname($params['filename'])).'/';

            $imgTag = '\!\[(.*)\]\((.*)\)';

            // Get the list of images i.e. tags like :  ![My nice image](.images/local.jpg)
            // and check if the file is local (in a subfolder of the note). If so, convert the relative
            //     ![My nice image](.images/local.jpg) to an absolute path
            //     ![My nice image](http://localhost/folder/subfolder/.images/local.jpg)

            $matches = array();
            if (preg_match_all('/'.$imgTag.'/', $markdown, $matches)) {
                $j = count($matches[0]);
                for ($i = 0; $i <= $j; $i++) {
                    if (isset($matches[2][$i])) {
                        // Add the fullpath only if the link to the image doesn't contains yet
                        // an hyperlink
                        if (strpos($matches[2][$i], '//') === false) {
                            $filename = $folderNote.str_replace('/', DS, $matches[2][$i]);
                            if ($aeFiles->fileExists($filename)) {
                                $markdown = str_replace($matches[0][$i], '!['.$matches[1][$i].']('.$folder.$matches[2][$i].')', $markdown);
                            } else {
                                /*<!-- build:debug -->*/
                                if ($aeSettings->getDebugMode()) {
                                    $aeDebug = \MarkNotes\Debug::getInstance();
                                    $aeDebug->here('DEBUG MODE --- '.$filename.' NOT FOUND');
                                }
                                /*<!-- endbuild -->*/
                            }
                        }//if (strpos('//', $matches[2][$i])===FALSE)
                    }
                }
            } // if (preg_match_all('/'.$imgTag.'/'

            // And process <img> tags
            $imgTag = '<img (.*)src *= *["\']([^"\']+["\']*)[\'|"]';

            $matches = array();
            if (preg_match_all('/'.$imgTag.'/', $markdown, $matches)) {
                $j = count($matches);
                for ($i = 0; $i <= $j; $i++) {
                    // Derive the image fullname ($folderNote.str_replace('/',DS,$matches[1][$i]))) and check if the file exists
                    if (isset($matches[2][$i])) {

                        // Add the fullpath only if the link to the image doesn't contains yet
                        // an hyperlink
                        if (strpos($matches[2][$i], '//') === false) {
                            $filename = $folderNote.str_replace('/', DS, $matches[2][$i]);

                            if ($aeFiles->fileExists($filename)) {
                                $img = $folder.$matches[2][$i];
                                $markdown = str_replace($matches[0][$i], '<img src="'.$img.'" '.$matches[1][$i], $markdown);
                            }
                        }
                    }
                }
            } // if (preg_match_all('/'.$imgTag.'/'
        } // if (isset($params['filename']))

        return $markdown;
    }

    /**
     * Read a markdown file and return its content.
     * Correctly handle encrypted informations
     *
     * $params['removeConfidential']    1 : when encrypted data should be displayed as "Confidential"
     *                                  0 : encrypted infos will be displayed
     */
    public function read(string $filename, array $params = null) : string
    {
        $aeEvents = \MarkNotes\Events::getInstance();

        if (mb_detect_encoding($filename)) {
            if (!file_exists($filename)) {
                $filename = utf8_decode($filename);
            }
        }

        $markdown = file_get_contents($filename);

        // --------------------------------
        // Call content plugins
        $aeEvents->loadPlugins('markdown');
        $args = array(&$markdown);
        $aeEvents->trigger('markdown.read', $args);
        $markdown = $args[0];
        // --------------------------------

        $aeFiles = \MarkNotes\Files::getInstance();
        $aeFunctions = \MarkNotes\Functions::getInstance();
        $aeSettings = \MarkNotes\Settings::getInstance();

        // Get the full path to this note
        $url = rtrim($aeFunctions->getCurrentURL(false, false), '/').'/'.rtrim($aeSettings->getFolderDocs(false), DS).'/';
        $noteFolder = $url.str_replace(DS, '/', dirname($params['filename'])).'/';

        // In the markdown file, two syntax are possible for images, the ![]() one or the <img src one
        // Be sure to have the correct relative path i.e. pointing to the folder of the note
        $matches = array();
        $markdown = self::setImagesAbsolute($markdown, $params);

        // And do it too for links to the files folder
        $markdown = str_replace('href=".files/', 'href="'.$noteFolder.'.files/', $markdown);

        if (isset($params['removeConfidential'])) {
            if ($params['removeConfidential'] === '1') {
                $markdown = $this->ShowConfidential($markdown);
            }
        }

        return $markdown;
    }
}
