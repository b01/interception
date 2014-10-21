<?php namespace Kshabazz\Tests\Interception;

use \Kshabazz\Interception\StreamWrappers\Http;

class HttpTest extends \PHPUnit_Framework_TestCase
{

	public function setUp()
	{
		\stream_wrapper_unregister( 'http' );
		Http::setSaveDir( FIXTURES_PATH . DIRECTORY_SEPARATOR );

		\stream_register_wrapper(
			'http',
			'\\Kshabazz\\Interception\\StreamWrappers\\Http',
			\STREAM_IS_URL
		);
	}

	public function test_http_interception_of_fopen()
	{
		$handle = \fopen( 'http://www.google.com', 'r' );
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
}
?>