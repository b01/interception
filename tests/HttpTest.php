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

	static public function tearDownAfterClass()
	{
		stream_wrapper_restore( 'http' );
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

	/**
	 * @uses Http::setSaveFilename()
	 */
	public function test_setSaveFile()
	{
		$fileName = 'example-102120140915';
		Http::setSaveFilename( $fileName );
		\file_get_contents( 'http://www.example.com/test' );
		$file = FIXTURES_PATH . DIRECTORY_SEPARATOR . $fileName . '.rsd';
		$this->assertTrue( \file_exists($file) );
	}

	/**
	 * @uses Http::setSaveFilename()
	 * @expectedException \PHPUnit_Framework_Error
	 * @expectedExceptionMessage A filename cannot contain the following characters
	 */
	public function test_setSaveFile_with_invalid_name()
	{
		$fileName = 'test,<>';
		Http::setSaveFilename( $fileName );
		$this->assertEquals( '', Http::getSaveFilename() );
	}

	/**
	 * @uses \Kshabazz\Interception\StreamWrappers\Http::setSaveDir()
	 */
	public function test_setSaveDir_with_invalid_dir()
	{
		// Set an invalid directory.
		Http::setSaveDir( 'test' );
		$dir = FIXTURES_PATH;
		$this->assertEquals( $dir, Http::getSaveDir() );
	}
}
?>