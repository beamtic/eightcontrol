<?php
/**
 *           Doorkeeper File Types
 *
 *              This class contains headers specific to supported file types.
 *
 *              It is generally a good practice to use file extensions,
 *              as it makes it easier for both users and software to identify a file
 *              without having to first analyze the file content.
 *
 *              This class assumes we are using file extensions.
 *
 *              Note. Additional headers can be added, but care should be taken to make sure
 *              they will not conflict with other code. Remember this: if you add a HTTP header in this class,
 *              it will be used on all static assets unless overwritten locally somewhere.
 *
 *
 *         @author Jacob (JacobSeated)
 */

namespace doorkeeper\lib\file_handler;

class file_types
{

    // Maximum time to cache static resources in seconds (604800=7 days)
    public $max_age_av = '604800';
    public $max_age_images = '2592000'; // Audio/video 84600=1 day, 2592000=30 days
    public $disable_caching = false;

    private $file_types = array();

    public function __construct()
    {
        $this->define_file_types();
    }
    /**
     * Check if the URL fragment has an extension
     *
     * @param string $url_fragment
     *
     */
    public function has_extension(string $url_fragment)
    {
        if (preg_match("|^.+\.([^\.]{1,64})|i", $url_fragment, $ext_match)) {
            return $ext_match["1"];
        } else {
            return false;
        }
    }
    /**
     * Return Headers specific to the File Type
     * Note. Some file types, such as audio or video, may have additional headers
     *       set by the file_handler class.
     * @param string $extension
     * @return array An array of HTTP headers associated with the file type.
     */
    public function get_file_headers(string $extension)
    {
        $shared_file_headers = array(
            'x-content-type-options' => 'nosniff',
            'accept-ranges' => 'bytes'
        );

        if (isset($this->file_types["$extension"])) {
            if ($this->disable_caching === true) {
                $this->file_types["$extension"]['cache-control'] = 'no-cache';
            }
            // Note. The keys on the left will overwrite those on the right
            $response_headers = $this->file_types["$extension"] + $shared_file_headers;
        } else {
            // The File Type was unknown, use 'application/octet-stream' to allow downloading the file
            $response_headers = array('content-type' => 'application/octet-stream', 'cache-control' => 'max-age=84600, private');
            if ($this->disable_caching === true) {
                $response_headers["$extension"]['cache-control'] = 'no-cache';
            }
        }
        // Return the correct headers for the given file type
        return $response_headers;
    }
    /**
     * Send headers directly for the requested file type.
     * Use with caution!
     * @param string $extension
     *
     */
    public function send_file_headers(string $extension)
    {
        $response_headers = $this->get_file_headers($extension);

        // Output the headers directly
        foreach ($response_headers as $header => $value) {
            header($header . ': ' . $value);
        }

    }
    /**
     * Define supported Mime Types
     * @return void
     */
    private function define_file_types()
    {

        // Note.: "public" resources may be shared among users by a chaching server
        // while "private" resources must not be shared.
        // max-age=84600 = 1 day in seconds
        // max-age=604800 = 7 days in seconds

        // Text Types
        $this->file_types = array(
            'txt' => array('content-type' => 'text/plain; charset=utf-8', 'cache-control' => 'max-age=84600, private'),
            'html' => array('content-type' => 'text/html; charset=utf-8', 'cache-control' => 'max-age=84600, private'),
            'rss' => array('content-type' => 'text/xml; charset=utf-8', 'cache-control' => 'max-age=84600, private'),
            'xml' => array('content-type' => 'application/xml; charset=utf-8', 'cache-control' => 'max-age=84600, private'),
            'xhtml' => array('content-type' => 'application/xhtml+xml; charset=utf-8', 'cache-control' => 'max-age=84600, private'),
            'css' => array('content-type' => '	text/css; charset=utf-8', 'cache-control' => 'max-age=84600, private'),
        );
        // Images
        $this->file_types = $this->file_types + array(
            'jpg' => array('content-type' => 'image/jpeg', 'cache-control' => 'max-age=' . $this->max_age_images . ', public'),
            'jpeg' => array('content-type' => 'image/jpeg', 'cache-control' => 'max-age=' . $this->max_age_images . ', public'),
            'png' => array('content-type' => 'image/png', 'cache-control' => 'max-age=' . $this->max_age_images . ', public'),
            'avif' => array('content-type' => 'image/avif', 'cache-control' => 'max-age=' . $this->max_age_images . ', public'),
            'webp' => array('content-type' => 'image/webp', 'cache-control' => 'max-age=' . $this->max_age_images . ', public'),
            'svg' => array('content-type' => 'image/svg+xml; charset=utf-8', 'cache-control' => 'max-age=' . $this->max_age_images . ', private'),
            'gif' => array('content-type' => 'image/gif', 'cache-control' => 'max-age=' . $this->max_age_images . ', private'),
        );
        // Audio and Video
        $this->file_types = $this->file_types + array(
            'mp3' => array('content-type' => 'audio/mpeg', 'cache-control' => 'max-age=' . $this->max_age_av . ', public'),
            'mp4' => array('content-type' => 'video/mp4', 'cache-control' => 'max-age=' . $this->max_age_av . ', public'),
            'wav' => array('content-type' => 'audio/wav', 'cache-control' => 'max-age=' . $this->max_age_av . ', public'),
            'ogg' => array('content-type' => 'application/ogg', 'cache-control' => 'max-age=' . $this->max_age_av . ', public'),
            'flac' => array('content-type' => 'audio/flac', 'cache-control' => 'max-age=' . $this->max_age_av . ', public'),
        );

        // Compressed files
        $this->file_types = $this->file_types + array(
            '7z' => array('content-type' => 'application/x-7z-compressed', 'cache-control' => 'max-age=84600, public'),
            'rar' => array('content-type' => 'application/x-rar-compressed', 'cache-control' => 'max-age=84600, public'),
            'zip' => array('content-type' => 'application/zip', 'cache-control' => 'max-age=84600, public'),
            'gz' => array('content-type' => 'application/x-gzip', 'cache-control' => 'max-age=84600, public'),
        );

        // Font files
        $this->file_types = $this->file_types + array(
            'woff' => array('content-type' => 'font/woff', 'cache-control' => 'max-age=84600, public'),
            'woff2' => array('content-type' => 'font/woff2', 'cache-control' => 'max-age=84600, public'),
            'ttf' => array('content-type' => 'application/x-font-ttf', 'cache-control' => 'max-age=84600, public'),
        );
    }

}