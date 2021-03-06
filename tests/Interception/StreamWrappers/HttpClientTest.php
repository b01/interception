<?php namespace Kshabazz\Tests\Interception\StreamWrappers;
/**
 * Test for use with Guzzle HTTP client.
 */

use \GuzzleHttp\Client,
	\Kshabazz\Interception\StreamWrappers\Http,
	\Kshabazz\Interception\GuzzleHandler;

class HttpClientTest extends \PHPUnit_Framework_TestCase
{
	/** @var string */
	private $fixtureDir;

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

	public function setUp()
	{
		$this->fixtureDir = \FIXTURES_PATH . DIRECTORY_SEPARATOR;
	}

	public function test_intercepting_ringphp_stream_handle_request()
	{
		$filename = 'ignore-ringphp-test';
		Http::persistSaveFile( $filename );
		$streamHandler = new GuzzleHandler();
		// Make the request.
		$streamHandler([
			'http_method' => 'GET',
			'scheme' => 'http',
			'uri' => '/',
			'headers' => [ 'Host' => ['www.example.com'] ],
			'client' => [
				// Turn SSL verify off, on by default.
				'verify' => FALSE,
			]
		]);
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
		$httpClient = new Client([
			'handler' => $streamHandler
		]);

		$httpClient->get(
			'http://www.example.com/',
			[
				'version' => '1.0',
				'verify' => FALSE,
			]
		);
		Http::clearPersistSaveFile();
		$requestIntercepted = \file_exists( FIXTURES_PATH . DIRECTORY_SEPARATOR . $filename . '-1.rsd' );
		$this->assertTrue( $requestIntercepted );
	}

	public function test_intercepting_guzzle_client_request_for_rss_xml()
	{
		$filename = 'rss-xml';
		$streamHandler = new GuzzleHandler();
		// Set the file to save to.
		Http::setSaveFilename( $filename );
		// Have Guzzle use the Interception stream handler, so request can be intercepted.
		$httpClient = new Client([
			'handler' => $streamHandler
		]);
		// Have Guzzle load some XML from an RSS feed.
		$httpClient->get(
			'http://www.quickenloans.com/blog/category/mortgage/mortgage-basics/feed'
		);
		// cleanup for the next test.
		Http::clearSaveFile();
		// Verify that the RSS feed request was save to a file.
		$filename = $this->fixtureDir . $filename . '.rsd';
		$this->assertFileExists( $filename );
	}
}
?>