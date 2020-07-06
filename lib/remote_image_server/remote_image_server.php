<?php

/**
 * 
 *      Doorkeeper Remote Webcam Server
 * 
 *         Class to serve up remote images locally
 *         The class comes with a build-in caching mechanism to keep HTTP requests down and minimize stress on remote servers.
 * 
 *         Useful for webcams and other image resources that update frequently.
 * 
 *         A camera probably should not update every second, but you could do that if you wanted. Just be careful!
 *         Downloading other people's images can too often is considered abusive!
 *
 *         @author Jacob (JacobSeated)
 */

namespace doorkeeper\lib\remote_image_server;

use Exception;

class remote_image_server
{
    // The response from the remote server
    private $response = array();


    private $cache_max_age = '120'; // In seconds

    // Supported image mime types
    private $image_types = array(
        'jpg' => 'image/jpeg', // https://beamtic.com/jpg-mime-type
        'jpeg' => 'image/jpg', // Same as jpeg
        'png' => 'image/png', // https://beamtic.com/png-mime-type
        'webp' => 'image/webp', // https://beamtic.com/webp-mime-type
    );
    // Template files for alternative images
    private $image_tpl = array('unavailable' => 'tpl/remote_image_server/unavailable.png');

    // Directory name of the cache
    private $cache_dir;
    private $cache_index_path;

    private $url, $cached_name = null, $base_path, $return_format = 'bin';

    // A custom header mostly used for debugging
    private $ris_header = 'remote_image_server: freshly downloaded image. Updated the local cache.';

    public function __construct($base_path)
    {
        $this->base_path = $base_path;
        $this->cache_index_path = $this->base_path . 'data/image_server_cache_index.json';
        $this->cache_dir_name = 'tmp/';
        $this->cache_dir = $this->base_path . $this->cache_dir_name;
    }

    /**
     * Returns an image string on success, and false on failure.
     * @param $url The URL for the webcam image (must point directly to the image served, and not a HTML page)
     * @param $cached_name the name of the image for the image cache; by default mp5($url) is used, but some images may require a unique name (changing urls or different parameters).
     * @return false|string
     */
    public function show(string $url, string $return_format = null, string $cached_name = null)
    {
        $this->cached_name = $cached_name;
        $this->url = $url;
        $this->cached_name = (null === $cached_name) ? md5($this->url) : $cached_name;
        $this->return_format = (isset($return_format)) ? $return_format : 'bin';

        // Show a cached copy of the image, if available and not expired
        $this->fetch_from_cache();

        // Attempt to open the URL
        $handle = fopen($url, "rb");
        if (false === $this->response['content'] = stream_get_contents($handle)) {
            fclose($handle);
            $this->respond_alt('unavailable');
        }
        $this->response['headers'] = $this->normalize_headers(stream_get_meta_data($handle));

        // Close handle before returning
        fclose($handle);

        // Check if the required response header was set
        if (!isset($this->response['headers']['content-type'])) {
            $this->respond_alt('unavailable');
        }
        // Update the cached image
        $this->update_cache();

        // Default content type
        $content_type = 'text/plain';

        // Disable caching
        header('Cache-Control: no-store');
        header($this->ris_header);
        if ($this->return_format == 'bin') {
            $content_type = $this->response['headers']['content-type'];
            $response_data = $this->response['content'];
        } elseif ($this->return_format == 'imglink') {
            $response_data = '<a href="' . $this->public_image_path . '" class="dk_served_image_link"><img src="' . $this->public_image_path . '" alt="" class="dk_served_image"></a>';
        } elseif ($this->return_format == 'img') {
            $response_data = '<img src="' . $this->public_image_path . '" alt="" class="dk_served_image">';
        }
        header('content-type: ' . $content_type);
        header('content-length: ' . strlen($response_data));
        echo $response_data;
        exit();
    }

    /**
     * Methos to show alternative images. I.e. If a cam is not avavailable.
     * @param string $alternative_image
     * @return void
     */
    public function respond_alt(string $alternative_image)
    {
        // The image is a local template image; possibly because the URL was not reachable.
        $this->ris_header = 'remote_image_server: local template image.';

        if ($this->return_format !== 'bin') {
            $this->public_image_path = $this->image_tpl["$alternative_image"];
            return true;
        }

        if (false === $local_image = file_get_contents($this->base_path . $this->image_tpl["$alternative_image"])) {
            throw new Exception("Unable to load alternative image, this can be caused by issues with local file system paths.");
        }

        header('Cache-Control: no-store');
        header('content-type: image/png');
        echo $local_image;
        exit();
    }
    /**
     * Let's normalize those MiXeD-Case headers to avoid errors...
     * @return array
     */
    private function normalize_headers(array $headers_of_unknown_case)
    {
        $headers_lower_case = array();
        $i = 0;
        foreach ($headers_of_unknown_case['wrapper_data'] as $key => &$value) {
            if (0 === $i) {
                $headers_lower_case['status'] = $value;
            } else {
                preg_match('/^([^:]+):([^\n]+)$/', $value, $matches);
                $lower_case_key = strtolower($matches[1]);
                $headers_lower_case["{$lower_case_key}"] = $matches[2];
            }
            ++$i;
        }
        return $headers_lower_case;
    }
    /**
     * Downloading images from other people's websites can be considered a bit abusive, so let's save a cached copy to minimize their server-load.
     * @return mixed
     */
    private function update_cache()
    {
        $file_extensions = array_flip($this->image_types);
        $content_type = trim($this->response['headers']['content-type']);
        $cached_image_path = $this->cache_dir . $this->cached_name . '.' . $file_extensions["$content_type"];

        if (false === file_put_contents($cached_image_path, $this->response['content'])) {
            throw new Exception('Unable to save cached image file. This can be due to lack of permissions.');
        }

        if (file_exists($this->cache_index_path)) {
            $json_cache_index = file_get_contents($this->cache_index_path);
            $json_cache_index = json_decode($json_cache_index, true);

            $json_cache_index = $json_cache_index + array(
                $this->cached_name => $cached_image_path
            );
            $json_cache_index = json_encode($json_cache_index);
        } else {
            $json_cache_index = json_encode(array(
                $this->cached_name => $cached_image_path
            ));
        }

        file_put_contents($this->cache_index_path, $json_cache_index);

        // If client requested HTML, return public url for cached image
        if ($this->return_format === 'img') {
            $this->public_image_path = $this->cache_dir_name . $this->cached_name . '.' . $file_extensions["$content_type"];
        }
        return true;
    }
    /**
     * Shows a cached copy of the image unless it has expired.
     */
    private function fetch_from_cache()
    {
        if (false === file_exists($this->cache_index_path)) {
            return false;
        }
        // Decode the index file
        $json_cache_index = file_get_contents($this->cache_index_path);
        $decoded_cache_index = json_decode($json_cache_index, true);

        // Check if a cached copy exist in the index
        if (false === isset($decoded_cache_index["$this->cached_name"])) {
            return false;
        }

        if (false === file_exists($decoded_cache_index["$this->cached_name"])) {
            $this->remove_from_cache_index($decoded_cache_index);
            return false;
        }

        // Check if cached copy is still valid
        $filetime = filemtime($decoded_cache_index["$this->cached_name"]);

        if (time() > ($filetime + $this->cache_max_age)) {
            // If the cached copy is outdated, attempt to update the cache
            $this->remove_from_cache_index($decoded_cache_index);
            unlink($decoded_cache_index["$this->cached_name"]);
            return false;
        }

        // Attempt to deliver the cached binary copy
        if (false === $cached_image = file_get_contents($decoded_cache_index["$this->cached_name"])) {
            throw new Exception('Unable to deliver cached copy. This is unexpected.');
        }

        preg_match('/\.([a-z]+)$/i', $decoded_cache_index["$this->cached_name"], $matches);
        $file_ext = $matches["1"];

        // The file was delivered from cache, without downloading the remote image
        $this->ris_header = 'remote_image_server: delivered from cache.';

        // If client requested a non-binary format
        if ($this->return_format !== 'bin') {
            $this->public_image_path = $this->cache_dir_name . $this->cached_name . '.' . $file_ext;
            return true;
        }

        header($this->ris_header);
        header('Cache-Control: no-store');
        header('content-type: ' . $this->image_types["$file_ext"]);
        echo $cached_image;
        exit();
    }
    /**
     * Updates the json index file, removing irrelevant records.
     * @return string
     */
    private function remove_from_cache_index(array $decoded_cache_index)
    {
        unset($decoded_cache_index["$this->cached_name"]);
        $json_cache_index = json_encode($decoded_cache_index);
        file_put_contents($this->cache_index_path, $json_cache_index);
    }
}
