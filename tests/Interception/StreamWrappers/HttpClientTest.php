<?php namespace Kshabazz\Tests\Interception\StreamWrappers;
/**
 * Test for use with Guzzle HTTP client.
 */

use \GuzzleHttp\Client,
	\GuzzleHttp\Ring\Client\StreamHandler,
	\Kshabazz\Interception\StreamWrappers\Http;

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
		// Notice: In order to get RingPHP to work, a path to GuzzleHttp\Ring\Client\StreamHandler::createStreamResource
		//         method must be applied.

		// Reason: RingPHP uses $http_response_header to retrieve header information for a request.
		//         Unfortunately I am not aware of any way to set this from a custom HTTP wrapper class.
		//         Until that is possible, You have to manually patch the RingPHP before running these test.
		//

		// Patch to apply:
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
		Http::persistSaveFile( $filename );
		$streamHandler = new StreamHandler();
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