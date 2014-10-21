<?php
/**
 * Load files necessary to run test.
 */
require_once __DIR__
	. DIRECTORY_SEPARATOR . '..'
	. DIRECTORY_SEPARATOR . 'vendor'
	. DIRECTORY_SEPARATOR . 'autoload.php';

// Set fixture path constant.
$fixturesPath = realpath( __DIR__ . DIRECTORY_SEPARATOR . 'fixtures' );
define( 'FIXTURES_PATH', $fixturesPath );

?>