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
		$filename = 'test-save-filename-';
		$nameChanged = Http::setSaveFilename($filename);
		// Clear the save file name so that it is not used for the next request.
		Http::clearSaveFile();
		$this->assertTrue( $nameChanged );
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
	 * @expectedExceptionMessage Unable to connect to test.example.com
	 */
	public function test_http_live_stream_trigger_error()
	{
		\fopen( 'http://test.example.com/', 'r' );
	}

	public function test_meta_data()
	{
		$handle = \fopen( 'http://www.example.com/', 'r' );
		\fread( $handle, 4096 );
		$metaData = \stream_get_meta_data( $handle );
		\fclose( $handle );
		$this->assertContains( 'HTTP/1.0 200 OK', $metaData['wrapper_data'][0] );
	}

	public function test_meta_data_key_exists()
	{
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
	public function test_saving_file_when_no_can_be_found()
	{
		$filename = 'server-not-found';
		Http::setSaveFilename( $filename );
		@\file_get_contents( 'http://test.example.com/', 'r' );
		$fileExists = \file_exists(
			FIXTURES_PATH . DIRECTORY_SEPARATOR . $filename . '.rsd'
		);
		$this->assertFalse( $fileExists, 'A rsd file was saved for a non-existing server.' );
	}
}
?>