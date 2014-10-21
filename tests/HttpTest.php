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
		stream_wrapper_restore( 'http' );
	}

	public function test_http_interception_of_fopen()
	{
		$handle = \fopen( 'http://www.example.com', 'r' );
		$content = \fread( $handle, 100 );
		\fclose( $handle );
		$this->assertContains( 'HTTP/1.0 200 OK', $content );
	}

	/**
	 * @expectedException \PHPUnit_Framework_Error
	 * @expectedExceptionMessage Only read mode is supported
	 */
	public function test_http_interception_of_fopen_invalid_mode()
	{
		\fopen( 'http://www.example.com', 'w' );
	}

	public function test_http_interception_of_file_get_contents()
	{
		$content = \file_get_contents( 'http://www.example.com' );
		$this->assertContains( 'HTTP/1.0 200 OK', $content );
	}

	/**
	 * @uses \Kshabazz\Interception\StreamWrappers\Http::setSaveFilename()
	 */
	public function test_setSaveFile()
	{
		$fileName = 'ignore-example-' . date('mdYhi');
		Http::setSaveFilename( $fileName );
		\file_get_contents( 'http://www.example.com' );
		$file = FIXTURES_PATH . DIRECTORY_SEPARATOR . $fileName . '.rsd';
		$this->assertTrue( \file_exists($file) );
		\unlink( $file );
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
	 * @uses \Kshabazz\Interception\StreamWrappers\Http::setSaveDir()
	 */
	public function test_setSaveDir_with_invalid_dir()
	{
		// Set an invalid directory.
		Http::setSaveDir( 'test' );
		$this->assertEquals( FIXTURES_PATH, Http::getSaveDir() );
	}

	/**
	 * This makes a real network call to example.com.
	 * @expectedException \PHPUnit_Framework_Error
	 * @expectedExceptionMessage Unable to connect to test.example.com
	 */
	public function test_http_live_stream_trigger_error()
	{
		Http::setSaveFilename( 'ignore-live-stream-not-found' );
		$handle = \fopen( 'http://test.example.com/', 'r' );
		$content = \fread( $handle, 100 );
		\fclose( $handle );
		$this->assertContains( 'HTTP/1.0 200 OK', $content );
	}
}
?>