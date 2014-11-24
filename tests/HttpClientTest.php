<?php namespace Kshabazz\Tests\Interception;
/**
 * Test for use with Guzzle HTTP client.
 */
use GuzzleHttp\Client;
use GuzzleHttp\Ring\Client\StreamHandler;
use Kshabazz\Interception\StreamWrappers\Http;
/**
 * Class GuzzleTest
 *
 * @package Kshabazz\Tests\Interception
 */
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
		// Notice: In order to get RingPHP to work, I have to add this to the GuzzleHttp\Ring\Client\StreamHandler::createStreamResource method:

		// Reason: RingPHP uses $http_response_header to retrieve header information for a request.
		//         Unfortunately I am not aware of any way to set this from a custom HTTP wrapper class.
		//         Until that is possible, You have to manually patch the RingPHP before running these test.
		//
//		$fopenStream = fopen($url, 'r', null, $context);
//		if (!is_array($http_response_header)) {
//			$metaData = stream_get_meta_data($fopenStream);
//			$i = 0;
//			$requestData = $metaData['wrapper_data'];
//			while (isset($requestData[$i])) {
//				$http_response_header[] = $requestData[$i];
//				$i++;
//			}
//		}
		$filename = 'ignore-ringphp-test';
		Http::setSaveFilename( $filename );
		$request = array(
			'http_method' => 'GET',
			'scheme' => 'http',
			'uri' => '/',
			'version' => '1.0',
			'headers' => array( 'Host' => array('www.example.com') ),
			'client' => array(
				// Turn SSL off so no HTTPS, on by default.
				'verify' => FALSE,
				'version' => '1.0'
			)
		);
		// Make the request.
		$streamHandler = new StreamHandler();
		$streamHandler( $request );
		// Verify the request was recorded.
		$requestIntercepted = \file_exists( FIXTURES_PATH . DIRECTORY_SEPARATOR . $filename .'.rsd' );
		$this->assertTrue( $requestIntercepted );

		return $streamHandler;
	}

	/**
	 * @depends test_intercepting_ringphp_stream_handle_request
	 */
	public function test_intercepting_guzzle_client_request( $streamHandler )
	{
		$filename = 'ignore-guzzle-test';
		Http::setSaveFilename( $filename );
		// Force Guzzle to use a PHP stream instead of cURL.
		$httpClient = new Client(
			array(
				'handler' => $streamHandler
			)
		);
		$response = $httpClient->get(
			'http://www.example.com/',
			array(
				'version' => '1.0',
				'verify' => FALSE,
			)
		);

		$requestIntercepted = \file_exists( FIXTURES_PATH . DIRECTORY_SEPARATOR . $filename . '.rsd' );
		$this->assertTrue( $requestIntercepted );
	}
}