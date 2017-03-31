<?php
/* REQUIRES PHP 7.x AT LEAST */
namespace MarkNotes\FileType;

defined('_MARKNOTES') or die('No direct access allowed');

class Markdown
{
    protected static $_instance = null;
    private $_aeSettings = null;

    public function __construct()
    {
        $this->_aeSettings=\MarkNotes\Settings::getInstance();

        return true;
    } // function __construct()

    public static function getInstance()
    {

        if (self::$_instance === null) {
            self::$_instance = new Markdown();
        }
        return self::$_instance;
    } // function getInstance()

    /**
     * From a markdown content, return an heading text (by default the ""# TEXT" i.e. the heading 1)
     */
    public function getHeadingText(string $markdown, string $heading = '#') : string
    {
        // Try to find a heading 1 and if so use that text for the title tag of the generated page
        $matches=array();
        $title='';

        try {
            preg_match("/".$heading." ?(.*)/", $markdown, $matches);
            $title = (count($matches)>0) ? trim($matches[1]) : '';

            // Be sure that the heading 1 wasn't type like   # MyHeadingOne # i.e. with a final #

            $title=ltrim(rtrim($title, $heading), $heading);
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
        $prefix=$this->_aeSettings->getTagPrefix();
        $markdown=str_replace($prefix, '', $markdown);

        // If matches is greater than zero, there is at least one <encrypt> tag found in the file content
        if (count($matches[0])>0) {
            $j=count($matches[0]);

            $i=0;

            for ($i; $i<$j; $i++) {
                $markdown=str_replace($matches[0][$i], '<strong class="confidential">'.$this->_aeSettings->getText('confidential', 'confidential').'</strong>', $markdown);
            }
        }

        return $markdown;
    } // function ShowConfidential

    /**
     * Read a markdown file and return its content.
     * Correctly handle encrypted informations
     *
     * $params['removeConfidential']    1 : when encrypted data should be displayed as "Confidential"
     *                                  0 : encrypted infos will be displayed
     */
    public function read(string $filename, array $params = null) : string
    {
        if (mb_detect_encoding($filename)) {
            if (!file_exists($filename)) {
                $filename=utf8_decode($filename);
            }
        }

        $markdown=file_get_contents($filename);

        // -----------------------------------------------------------------------
        // URL Cleaner : Make a few cleaning like replacing space char in URL or in image source
        // Replace " " by "%20"

        $matches=array();
        if (preg_match_all('/<img *src *= *[\'|"]([^\'|"]*)/', $markdown, $matches)) {
            foreach ($matches[1] as $match) {
                $sMatch=str_replace(' ', '%20', $match);
                $markdown=str_replace($match, $sMatch, $markdown);
            }
        }

        // And do the same for links
        $matches=array();
        if (preg_match_all('/<a *href *= *[\'|"]([^\'|"]*)/', $markdown, $matches)) {
            foreach ($matches[1] as $match) {
                $sMatch=str_replace(' ', '%20', $match);
                $markdown=str_replace($match, $sMatch, $markdown);
            }
        }

        $noteFolder=rtrim($this->_aeSettings->getFolderDocs(false), DS).'/'.str_replace(DS, '/', dirname($params['filename'])).'/';

        // In the markdown file, two syntax are possible for images, the ![]() one or the <img src one
        // Be sure to have the correct relative path i.e. pointing to the folder of the note
        $markdown=str_replace('![](.images/', '![]('.$noteFolder.'.images/', $markdown);
        $markdown=str_replace('src=".images/', 'src="'.$noteFolder.'.images/', $markdown);

        // And do it too for links to the files folder
        $markdown=str_replace('href=".files/', 'href="'.$noteFolder.'.files/', $markdown);

        // Initialize the encryption class
        $aesEncrypt=new \MarkNotes\Encrypt($this->_aeSettings->getEncryptionPassword(), $this->_aeSettings->getEncryptionMethod());
        $markdown=$aesEncrypt->HandleEncryption($filename, $markdown);

        if (isset($params['removeConfidential'])) {
            if ($params['removeConfidential']==='1') {
                $markdown = $this->ShowConfidential($markdown);
            }
        }

        return $markdown;
    } // function read()
} // class Functions