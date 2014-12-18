<?php namespace Kshabazz\Tests\Interception\StreamWrappers;
/**
 * Test for use with Guzzle HTTP client.
 */

use \GuzzleHttp\Client,
	\Kshabazz\Interception\StreamWrappers\Http,
	\Kshabazz\Interception\GuzzleHandler;

class HttpClientTest extends \PHPUnit_Framework_TestCase
{
	static public function setUpBeforeClass()
	{
		\stream_wrapper_unregister( 'http' );
		\stream_register_wrapper(
			'http',
			'\\Kshabazz\\Interception\\StreamWrappers\\Http',
			\STREAM_IS_URL
		);
		Http::setSaveDir( FIXTURES_PATH );
	}

	static public function tearDownAfterClass()
	{
		// Restore the built-in HTTP wrapper for other unit tests.
		\stream_wrapper_restore( 'http' );
		// Clean up all ignore files.
		$removeFiles = \glob( FIXTURES_PATH . DIRECTORY_SEPARATOR . 'ignore*' );
		\array_map( 'unlink', $removeFiles );
	}

	public function test_intercepting_ringphp_stream_handle_request()
	{
		$filename = 'ignore-ringphp-test';
		Http::persistSaveFile( $filename );
		$streamHandler = new GuzzleHandler();
		// Make the request.
		$streamHandler(array(
			'http_method' => 'GET',
			'scheme' => 'http',
			'uri' => '/',
			'headers' => array( 'Host' => array('www.example.com') ),
			'client' => array(
				// Turn SSL verify off, on by default.
				'verify' => FALSE,
			)
		));
		Http::clearPersistSaveFile();
		// Verify the request was recorded.
		$requestIntercepted = \file_exists( FIXTURES_PATH . DIRECTORY_SEPARATOR . $filename . '-1.rsd' );
		$this->assertTrue( $requestIntercepted );

		return $streamHandler;
	}

	/**
	 * @depends test_intercepting_ringphp_stream_handle_request
	 */
	public function test_intercepting_guzzle_client_request( $streamHandler )
	{
		$filename = 'ignore-guzzle-test';
		Http::persistSaveFile( $filename );
		// Force Guzzle to use a PHP stream instead of cURL.
		$httpClient = new Client(array(
			'handler' => $streamHandler
		));

		$httpClient->get(
			'http://www.example.com/',
			array(
				'version' => '1.0',
				'verify' => FALSE,
			)
		);
		Http::clearPersistSaveFile();
		$requestIntercepted = \file_exists( FIXTURES_PATH . DIRECTORY_SEPARATOR . $filename . '-1.rsd' );
		$this->assertTrue( $requestIntercepted );
	}
}
?>