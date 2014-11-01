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
		// Clean up all ignore files.
		$removeFiles = \glob( FIXTURES_PATH . DIRECTORY_SEPARATOR . 'ignore*' );
		\array_map( 'unlink',$removeFiles );
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
	 * @expectedExceptionMessage fopen(http://www.example.com): failed to open stream: HTTP wrapper does not support writeable connections
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
	 * This makes a real network request to example.com.
	 *
	 * @expectedException \PHPUnit_Framework_Error
	 * @expectedExceptionMessage fsockopen(tcp://test.example.com): php_network_getaddresses: getaddrinfo failed: No such host is known.
	 */
	public function test_http_live_stream_trigger_error()
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

	/**
	 * @depends test_meta_data_from_resource
	 */
	public function test_meta_data_when_using_a_file( $filename )
	{
		Http::setSaveFilename( $filename );
		$handle = \fopen( 'http://www.example.com/', 'r' );
		\fread( $handle, 4096 );
		$metaData = \stream_get_meta_data( $handle );
		\fclose( $handle );
		$this->assertContains( 'HTTP/1.0 200 OK', $metaData['wrapper_data'][0] );
	}

	public function test_meta_data_key_exists()
	{
		Http::setSaveFilename( 'www-example-com' );
		$handle = \fopen( 'http://www.example.com/', 'r' );
		\fread( $handle, 4096 );
		$metaData = \stream_get_meta_data( $handle );
		\fclose( $handle );
		$this->assertArrayHasKey( 0, $metaData['wrapper_data'] );
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
		@\file_get_contents( 'http://test.example.com/', 'r' );
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
}
?>