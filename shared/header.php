<?php
/*
 *         Doorkeeper
 *
 *           This header file contains the absolute minimum required
 *           to initialize classes in Doorkeeper based projects
 *
 *           BASE_PATH = Absolute path on local file system for the project root
 *                       I.e.: var/www/MyProject
 *
 *                       It can be defined at the top of your project index file (Composition Root) like this:
 *                       define('BASE_PATH', rtrim(preg_replace('#[/\\\\]{1}#', '/', realpath(dirname(__FILE__))), '/') . '/');
 *
 *           The autoloader makes it possible to easily use classes
 *           without the need to manually include them.
 *
 */

require BASE_PATH . 'shared/Psr4AutoloaderClass.php';

$loader = new phpfig\Psr4AutoloaderClass;

// Add the path for the doorkeeper namespace (location of all doorkeeper classes)
$loader->addNamespace('doorkeeper\lib', BASE_PATH . 'lib');

$loader->register(); // Registers the autoloader