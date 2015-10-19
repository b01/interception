<?php namespace Kshabazz\Interception;
/**
 * Load files necessary to run test.
 */

require_once __DIR__
	. DIRECTORY_SEPARATOR . '..'
	. DIRECTORY_SEPARATOR . 'vendor'
	. DIRECTORY_SEPARATOR . 'autoload.php';

// Set fixture path constant.
\define( 'FIXTURES_PATH', \realpath(__DIR__ . DIRECTORY_SEPARATOR . 'fixtures') );
\define(
	'Kshabazz\\Tests\\Interception\\HTTP_STREAM_WRAPPER',
	'\\Kshabazz\\Interception\\StreamWrappers\\Http'
);

?>