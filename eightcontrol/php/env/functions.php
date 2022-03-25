<?php
/**
 *              global functions for eightcontrol
 *                and PHP8 polyfills for limited backwards compatibility
 *
 *              this file should be required from the composition root (E.g.: index.php)
 */


if (!function_exists('str_contains')) {
    /**
     * Checks if needle exists in haystack
     * @param string $haystack
     * @param string $needle
     * @return bool
     */
    function str_contains(string $haystack, string $needle) : bool {
        return (false !== strpos($haystack, $needle)) ? true : false;
    }
}