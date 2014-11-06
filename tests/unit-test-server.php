<?php
/**
 * Start the Built-in PHP web server for unit test sake.
 * Kills the process when done.
 */
$fixturesWebPath = FIXTURES_PATH . DIRECTORY_SEPARATOR . 'web';
$phpServerCmd = 'start php -S localhost:9876 -t ' . $fixturesWebPath;
var_dump($phpServerCmd);
// Start PHP web server.
$startPHPserver = \exec( $phpServerCmd, $output, $returnStatus );
// Check status of PHP server process.
$checkStart = \exec( 'TASKLIST /FI "IMAGENAME eq php-web-server"' );
// Status update.
var_dump( $startPHPserver, $output, $return, $checkStart );
// Kill PHP web server.
$killStart = \exec( 'TASKKILL /F /IM "samp-server.exe"' );
// Check status of PHP server process.
$checkStart = \exec( 'TASKLIST /FI "IMAGENAME eq php-web-server"' );
// Status update.
var_dump( $killStart, $checkStart );

// METHOD 5:
// Command.
$phpServerCmd = 'start /b "phpunit" "php" -S localhost:9876 -t ' . $fixturesWebPath;
$phpServerCmd = 'php';
// Options
$resourceSpec = [
	0 => [ 'pipe','r' ],
	1 => [ 'pipe', 'w' ],
	2 => [ 'file', FIXTURES_PATH . DIRECTORY_SEPARATOR . 'test-server.log', 'a' ],
];
$others = [
	'bypass_shell' => TRUE
];
// Get a handle to a command shell.
$console = \proc_open( $phpServerCmd, $resourceSpec, $resources, NULL, NULL, $others );

// Read output from the console.
echo "\noutput: " . \stream_get_contents( $resources[1] ) . "\n";
var_dump( $content);

// Close all resource handles.
\fclose( $resources[0] );
\fclose( $resources[1] );

// Kill the console process.
$exitCode = \proc_close( $console );
?>