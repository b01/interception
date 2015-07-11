<?php namespace Kshabazz\Interception;
/**
 * This file is a copy of the GuzzleHttp\Ring\Client\StreamHandler
 * It patches the StreamHandler::createStreamResource method to allow
 * custom HTTP wrappers.
 *
 * Notice: In order to get RingPHP to work, a path to GuzzleHttp\Ring\Client\StreamHandler::createStreamResource
 * method must be applied.
 *
 * Reason: RingPHP uses $http_response_header to retrieve header information for a request.
 * Unfortunately I am not aware of any way to set this from a custom HTTP wrapper class.
 * Until that is possible, You have to manually patch the RingPHP before running these test.
 *
 * Patch:
 * $fopenStream = fopen($url, 'r', null, $context);
 * if (!is_array($http_response_header)) {
 *     $metaData = stream_get_meta_data($fopenStream);
 *     $i = 0;
 *     $requestData = $metaData['wrapper_data'];
 *     while (isset($requestData[$i])) {
 *         $http_response_header[] = $requestData[$i];
 *         $i++;
 *     }
 * }
 */

use GuzzleHttp\Ring\Client\ClientUtils;
use GuzzleHttp\Ring\Core;
use GuzzleHttp\Ring\Exception\ConnectException;
use GuzzleHttp\Ring\Future\CompletedFutureArray;
use GuzzleHttp\Stream\InflateStream;
use GuzzleHttp\Stream\StreamInterface;
use GuzzleHttp\Stream\Stream;
use GuzzleHttp\Stream\Utils;

/**
 * Class GuzzleHandler
 *
 * @package Kshabazz\Interception
 * @codeCoverageIgnore
 */
class GuzzleHandler
{
	private $options;

	public function __construct(array $options = [])
	{
		$this->options = $options;
	}

	public function __invoke(array $request)
	{
		$url = Core::url($request);
		Core::doSleep($request);

		try {
			// Does not support the expect header.
			$request = Core::removeHeader($request, 'Expect');
			$stream = $this->createStream($url, $request, $headers);
			return $this->createResponse($request, $url, $headers, $stream);
		} catch (\Exception $e) {
			return $this->createErrorResponse($url, $e);
		}
	}

	private function createResponse(array $request, $url, array $hdrs, $stream)
	{
		$parts = explode(' ', array_shift($hdrs), 3);
		$response = [
			'status'         => $parts[1],
			'reason'         => isset($parts[2]) ? $parts[2] : null,
			'headers'        => Core::headersFromLines($hdrs),
			'effective_url'  => $url,
		];

		$stream = $this->checkDecode($request, $response, $stream);

		// If not streaming, then drain the response into a stream.
		if (empty($request['client']['stream'])) {
			$dest = isset($request['client']['save_to'])
				? $request['client']['save_to']
				: fopen('php://temp', 'r+');
			$stream = $this->drain($stream, $dest);
		}

		$response['body'] = $stream;

		return new CompletedFutureArray($response);
	}

	private function checkDecode(array $request, array $response, $stream)
	{
		// Automatically decode responses when instructed.
		if (!empty($request['client']['decode_content'])) {
			switch (Core::firstHeader($response, 'Content-Encoding', true)) {
				case 'gzip':
				case 'deflate':
					$stream = new InflateStream(Stream::factory($stream));
					break;
			}
		}

		return $stream;
	}

	/**
	 * Drains the stream into the "save_to" client option.
	 *
	 * @param resource                        $stream
	 * @param string|resource|StreamInterface $dest
	 *
	 * @return Stream
	 * @throws \RuntimeException when the save_to option is invalid.
	 */
	private function drain($stream, $dest)
	{
		if (is_resource($stream)) {
			if (!is_resource($dest)) {
				$stream = Stream::factory($stream);
			} else {
				stream_copy_to_stream($stream, $dest);
				fclose($stream);
				rewind($dest);
				return $dest;
			}
		}

		// Stream the response into the destination stream
		$dest = is_string($dest)
			? new Stream(Utils::open($dest, 'r+'))
			: Stream::factory($dest);

		Utils::copyToStream($stream, $dest);
		$dest->seek(0);
		$stream->close();

		return $dest;
	}

	/**
	 * Creates an error response for the given stream.
	 *
	 * @param string        $url
	 * @param \Exception $e
	 *
	 * @return array
	 */
	private function createErrorResponse($url, \Exception $e)
	{
		// Determine if the error was a networking error.
		$message = $e->getMessage();

		// This list can probably get more comprehensive.
		if (strpos($message, 'getaddrinfo') // DNS lookup failed
			|| strpos($message, 'Connection refused')
		) {
			$e = new ConnectException($e->getMessage(), 0, $e);
		}

		return [
			'status'        => null,
			'body'          => null,
			'headers'       => [],
			'effective_url' => $url,
			'error'         => $e
		];
	}

	/**
	 * Create a resource and check to ensure it was created successfully
	 *
	 * @param callable $callback Callable that returns stream resource
	 *
	 * @return resource
	 * @throws \Exception on error
	 */
	private function createResource(callable $callback)
	{
		// Turn off error reporting while we try to initiate the request
		$level = error_reporting(0);
		$resource = call_user_func($callback);
		error_reporting($level);

		// If the resource could not be created, then grab the last error and
		// throw an exception.
		if (!is_resource($resource)) {
			$message = 'Error creating resource: ';
			foreach ((array) error_get_last() as $key => $value) {
				$message .= "[{$key}] {$value} ";
			}
			throw new \Exception(trim($message));
		}

		return $resource;
	}

	private function createStream(
		$url,
		array $request,
		&$http_response_header
	) {
		static $methods;
		if (!$methods) {
			$methods = array_flip(get_class_methods(__CLASS__));
		}

		// HTTP/1.1 streams using the PHP stream wrapper require a
		// Connection: close header
		if ((!isset($request['version']) || $request['version'] == '1.1')
			&& !Core::hasHeader($request, 'Connection')
		) {
			$request['headers']['Connection'] = ['close'];
		}

		// Ensure SSL is verified by default
		if (!isset($request['client']['verify'])) {
			$request['client']['verify'] = true;
		}

		$params = [];
		$options = $this->getDefaultOptions($request);

		if (isset($request['client'])) {
			foreach ($request['client'] as $key => $value) {
				$method = "add_{$key}";
				if (isset($methods[$method])) {
					$this->{$method}($request, $options, $value, $params);
				}
			}
		}

		return $this->createStreamResource(
			$url,
			$request,
			$options,
			$this->createContext($request, $options, $params),
			$http_response_header
		);
	}

	private function getDefaultOptions(array $request)
	{
		$headers = "";
		foreach ($request['headers'] as $name => $value) {
			foreach ((array) $value as $val) {
				$headers .= "$name: $val\r\n";
			}
		}

		$context = [
			'http' => [
				'method'           => $request['http_method'],
				'header'           => $headers,
				'protocol_version' => isset($request['version']) ? $request['version'] : 1.1,
				'ignore_errors'    => true,
				'follow_location'  => 0,
			],
		];

		$body = Core::body($request);
		if (isset($body)) {
			$context['http']['content'] = $body;
			// Prevent the HTTP handler from adding a Content-Type header.
			if (!Core::hasHeader($request, 'Content-Type')) {
				$context['http']['header'] .= "Content-Type:\r\n";
			}
		}

		$context['http']['header'] = rtrim($context['http']['header']);

		return $context;
	}

	private function applyCustomOptions(array $request, array &$options)
	{
		if (!isset($request['client']['stream_context'])) {
			return;
		}

		if (!is_array($request['client']['stream_context'])) {
			throw new \Exception('stream_context must be an array');
		}

		$options = array_replace_recursive(
			$options,
			$request['client']['stream_context']
		);
	}

	private function createContext(array $request, array $options, array $params)
	{
		$this->applyCustomOptions($request, $options);
		return $this->createResource(
			function () use ($request, $options, $params) {
				return stream_context_create($options, $params);
			},
			$request,
			$options
		);
	}

	private function createStreamResource(
		$url,
		array $request,
		array $options,
		$context,
		&$http_response_header
	) {
		return $this->createResource(
			function () use ($url, &$http_response_header, $context) {
				if (false === strpos($url, 'http')) {
					trigger_error("URL is invalid: {$url}", E_USER_WARNING);
					return null;
				}
				$fopenStream = fopen($url, 'r', null, $context);
				if (!is_array($http_response_header)) {
					$metaData = stream_get_meta_data($fopenStream);
					$i = 0;
					$requestData = $metaData['wrapper_data'];
					while (isset($requestData[$i])) {
						$http_response_header[] = $requestData[$i];
						$i++;
					}
				}
				return $fopenStream;
			},
			$request,
			$options
		);
	}
}