<?php namespace Kshabazz\Tests\Interception;

use \Kshabazz\Interception\StreamWrappers\Http;

class HttpTest extends \PHPUnit_Framework_TestCase
{
	static public function setUpBeforeClass()
	{
		\stream_wrapper_unregister( 'http' );
		Http::setSaveDir( FIXTURES_PATH );

		\stream_register_wrapper(
			'http',
			'\\Kshabazz\\Interception\\StreamWrappers\\Http',
			\STREAM_IS_URL
		);
	}

	static public function tearDownAfterClass()
	{
		\stream_wrapper_restore( 'http' );
		// TODO: Move this where it will execute after the entire suite runs.
		// Clean up all ignore files.
		$removeFiles = \glob( FIXTURES_PATH . DIRECTORY_SEPARATOR . 'ignore*' );
		\array_map( 'unlink', $removeFiles );
	}

	public function test_http_interception_of_fopen_using_tcp()
	{
		$filename = 'ignore-www-example-com-fopen';
		Http::setSaveFilename( $filename );
		$handle = \fopen( 'http://www.example.com', 'r' );
		$content = \fread( $handle, 100 );
		\fclose( $handle );
		$this->assertContains( '<title>Example Domain</title>', $content );
		return $filename;
	}

	/**
	 * By using the same filename from the previous test, we can check that Interception loads request from file.
	 *
	 * @depends test_http_interception_of_fopen_using_tcp
	 */
	public function test_http_interception_of_fopen_using_file( $filename )
	{
		Http::setSaveFilename( $filename );
		$handle = \fopen( 'http://www.example.com', 'r' );
		$content = \fread( $handle, 100 );
		\fclose( $handle );
		$this->assertContains( '<title>Example Domain</title>', $content );
	}

	/**
	 * @expectedException \PHPUnit_Framework_Error
	 * @expectedExceptionMessage http://www.example.com: failed to open stream: HTTP wrapper does not support writable connections
	 */
	public function test_http_interception_of_fopen_invalid_mode()
	{
		\fopen( 'http://www.example.com', 'w' );
	}

	public function test_http_interception_of_file_get_contents_using_tcp()
	{
		$filename = 'ignore-www-example-com-file_get_contents';
		Http::setSaveFilename( $filename );
		$content = \file_get_contents( 'http://www.example.com' );
		$this->assertContains( '<title>Example Domain</title>', $content );
		return $filename;
	}

	/**
	 * @depends test_http_interception_of_file_get_contents_using_tcp
	 */
	public function test_http_interception_of_file_get_contents_file( $filename )
	{
		Http::setSaveFilename( $filename );
		$content = \file_get_contents( 'http://www.example.com' );
		$this->assertContains( '<title>Example Domain</title>', $content );
	}

	/**
	 * @uses \Kshabazz\Interception\StreamWrappers\Http::setSaveFilename()
	 * @expectedException \PHPUnit_Framework_Error
	 * @expectedExceptionMessage A filename cannot contain the following characters
	 */
	public function test_setSaveFilename_with_invalid_name()
	{
		$fileName = 'test,<>';
		$this->assertFalse( Http::setSaveFilename($fileName) );
	}

	/**
	 * @covers \Kshabazz\Interception\StreamWrappers\Http::setSaveDir()
	 * @uses \Kshabazz\Interception\StreamWrappers\Http::getSaveDir()
	 */
	public function test_setSaveDir_with_invalid_dir()
	{
		// Set an invalid directory.
		Http::setSaveDir( 'test' );
		$this->assertEquals( FIXTURES_PATH, Http::getSaveDir() );
	}

	/**
	 * Test error thrown for bad host name.
	 *
	 * @expectedException \PHPUnit_Framework_Error
	 * @expectedExceptionMessage No such host is known.
	 */
	public function test_http_socket_unknown_host_triggers_an_error()
	{
		Http::setSaveFilename( 'www-example-com-3' );
		\fopen( 'http://test.example.com/', 'r' );
	}

	public function test_meta_data_from_resource()
	{
		$filename = 'ignore-www-example-com-5';
		Http::setSaveFilename( $filename );
		$handle = \fopen( 'http://www.example.com/', 'r' );
		\fread( $handle, 4096 );
		$metaData = \stream_get_meta_data( $handle );
		\fclose( $handle );
		$this->assertContains( 'HTTP/1.0 200 OK', $metaData['wrapper_data'][0] );
		return $filename;
	}

	public function test_stream_get_meta_data_when_using_a_socket()
	{
		$filename = 'ignore-www-example-com-via-socket';
		Http::setSaveFilename( $filename );
		$handle = \fopen( 'http://www.example.com/', 'r' );
		\fread( $handle, 4096 );
		$metaData = \stream_get_meta_data( $handle );
		\fclose( $handle );
		// Headers are stored at index 0 of the wrapper data.
		$this->assertArrayHasKey( 0, $metaData['wrapper_data'] );
		$this->assertEquals( 'HTTP/1.0 200 OK', $metaData['wrapper_data'][0] );
		return $filename;
	}

	/**
	 * @depends test_stream_get_meta_data_when_using_a_socket
	 */
	public function test_stream_get_meta_data_when_using_a_file( $filename )
	{
		Http::setSaveFilename( $filename );
		$handle = \fopen( 'http://www.example.com/', 'r' );
		\fread( $handle, 4096 );
		$metaData = \stream_get_meta_data( $handle );
		\fclose( $handle );
		$this->assertEquals( 'HTTP/1.0 200 OK', $metaData['wrapper_data'][0] );
	}

	public function test_setSaveDir()
	{
		$this->assertTrue( Http::setSaveDir(FIXTURES_PATH) );
	}

	/**
	 * This makes a real network request to test.example.com,
	 * which should be a server not found error.
	 */
	public function test_saving_file_when_no_server_can_be_found()
	{
		$filename = 'server-not-found';
		Http::setSaveFilename( $filename );
		@\file_get_contents( 'http://test.example.com/' );
		$fileExists = \file_exists(
			FIXTURES_PATH . DIRECTORY_SEPARATOR . $filename . '.rsd'
		);
		$this->assertFalse( $fileExists, 'A rsd file was saved for a non-existing server.' );
	}

	public function test_getting_response_headers_without_reading_from_the_stream()
	{
		Http::setSaveFilename( 'www-example-com' );
		$handle = \fopen( 'http://www.example.com/', 'r' );
		$metaData = \stream_get_meta_data( $handle );
		// Hidden feature:
		// Calling Http::setSaveFilename will load the request from that file if it exists.
		// But you can also call it again, before fclose, to save the content elsewhere.
		Http::setSaveFilename( 'ignore-www-example-com-partial' );

		\fclose( $handle );
		$this->assertArrayHasKey( 0, $metaData['wrapper_data'] );
	}

	/**
	 * @expectedException \Kshabazz\Interception\InterceptionException
	 * @expectedExceptionMessage Please set a filename
	 */
	public function test_throw_exception_when_setSaveFilename_not_called_before_stream_open()
	{
		\fopen( 'http://www.example.com/', 'r' );
	}

	public function test_setting_request_headers()
	{
		// The test works but you have to manually start the PHP web server before you run it.
		$this->markTestIncomplete( 'Build a windows batch script to start/stop the PHP built-in server without it blocking this process.' );
		$params = 'test=1234';
		Http::setSaveFilename( 'ignore-headers-set' );
		$context = \stream_context_create([
			'http' => [
				'method' => 'POST',
		        'header' => \implode("\r\n", [
			        'content-type: application/x-www-form-urlencoded',
			        'content-length: ' . strlen( $params ),
			        'TEST: 4321',
		        ]) . "\r\n",
		        'content' => 'test=1234'
			]
		]);
		$connection = \fopen( 'http://localhost:9876/test.php', 'r', FALSE, $context );
		$content = \fread( $connection, 8000 );
		$this->assertContains( '1234', $content );
	}

	/**
	 * When there are no headers, we will get stuck in stream_open, due to the while loop in populateResponseHeaders()
	 */
	public function test_no_headers_with_stream_get_meta_data()
	{
		Http::setSaveFilename( 'no-header' );
		$handle = \fopen( 'http://www.example.com/', 'r' );
		\fread( $handle, 4096 );
		$metaData = \stream_get_meta_data( $handle );
		\fclose( $handle );
		// Headers are stored at index 0 of the wrapper data.
		$this->assertNull( $metaData['wrapper_data'][0] );
	}

	public function test_http_protocol_1_1()
	{
		Http::setSaveFilename( 'ignore-http-protocol-1-1' );
		$context = \stream_context_create([
				'http' => [
					'method' => 'GET',
					'protocol_version' => '1.1'
				]
			]);
		$connection = \fopen( 'http://www.example.com/', 'r', FALSE, $context );
		$metaData = \stream_get_meta_data( $connection );
		$this->assertContains( 'HTTP/1.1 200 OK', $metaData[ 'wrapper_data' ][0] );
	}

	public function test_http_protocol_1_1_with_connection_close()
	{
		Http::setSaveFilename( 'ignore-http-protocol-1-1-close-connection-header' );
		$context = \stream_context_create([
				'http' => [
					'header' => 'Connection: close',
					'method' => 'GET',
					'protocol_version' => '1.1'
				]
			]);
		$connection = \fopen( 'http://www.example.com/', 'r', FALSE, $context );
		$metaData = \stream_get_meta_data( $connection );
		\fclose( $connection );
		$this->assertContains( 'HTTP/1.1 200 OK', $metaData['wrapper_data'][0] );
	}

	public function test_https_protocol_1_1_with_connection_close()
	{
		Http::setSaveFilename( 'ignore-https-protocol-1-1-close-connection-header' );
		$context = \stream_context_create([
				'http' => [
					'header' => 'Connection: close',
					'method' => 'GET',
					'protocol_version' => '1.1'
				],
		        'ssl' => [
			        'verify' => FALSE
		        ]
			]);
		$connection = \fopen( 'http://www.example.com/', 'r', FALSE, $context );
		$metaData = \stream_get_meta_data( $connection );
		\fclose( $connection );
		$this->assertContains( 'HTTP/1.1 200 OK', $metaData['wrapper_data'][0] );
	}
}
?>