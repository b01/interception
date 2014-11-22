<?php namespace Kshabazz\Interception\StreamWrappers;
/**
 * Intercept HTTP request when \fopen or \file_get_contents is called.
 */

use Kshabazz\Interception\InterceptionException;

/**
 * Class Http
 *
 * @package Kshabazz\Interception\StreamWrappers
 */
class Http implements \ArrayAccess
{
	const
		/** @var int */
		RESOURCE_TYPE_FILE = 1,
		/** @var int */
		RESOURCE_TYPE_STREAM = 21;

	public
		/** @var resource */
		$context,
		/** @var int cursor position in the stream. */
		$position;

	public static
		/** @var string Directory to save raw files. */
		$saveDir = '.',
		/** @var string */
		$saveFile = '';

	private
		/** @var string */
		$content,
		/** @var bool */
		$isHeadersSet,
		/** @var resource */
		$resource,
		/** @var string */
		$url,
		/** @var array */
		$wrapperData;

	/**
	 * Constructor
	 */
	public function __construct()
	{
		$this->content = '';
		$this->position = 0;
		$this->resource = NULL;
		$this->url = NULL;
		$this->wrapperData = [];
	}

	/**
	 * Allow access via indices.
	 *
	 * @param mixed $pKey
	 * @return bool
	 */
	public function offsetExists( $pKey )
	{
		return \array_key_exists( $pKey, $this->wrapperData );
	}

	/**
	 * Allow access via indices.
	 *
	 * @param mixed $pKey
	 * @return bool
	 */
	public function offsetGet( $pKey )
	{
		return $this->wrapperData[ $pKey ];
	}

	/**
	 * @codeCoverageIgnore
	 * @param mixed $pKey
	 * @param mixed $pValue
	 */
	public function offsetSet( $pKey, $pValue )
	{
	}

	/**
	 * @codeCoverageIgnore
	 * @param mixed $pKey
	 */
	public function offsetUnset( $pKey )
	{
	}

	/**
	 * Close the resource.
	 *
	 * @return void
	 */
	public function stream_close()
	{
		if ( !\is_resource($this->resource) )
		{
			return;
		}

		\fclose( $this->resource );

		if ( !empty($this->content) )
		{
			$saveFile = $this->getSaveFile();
			\file_put_contents( $saveFile, $this->content );
			// Reset so we do not overwrite unintentionally for the next request.
			self::clearSaveFile();
		}
	}

	/**
	 * @return bool
	 */
	public function stream_eof()
	{
		return \feof( $this->resource );
	}

	/**
	 * Open a stream resource.
	 *
	 * @param string $pPath
	 * @param string $pMode
	 * @param int $pFlags
	 * @param string $pOpenedPath
	 * @return bool
	 * @throws \Exception
	 */
	public function stream_open( $pPath, $pMode, $pFlags, &$pOpenedPath )
	{
		if( 'r' !== $pMode && 'rb' !== $pMode )
		{
			\trigger_error(
				'fopen(' . $pPath . '): failed to open stream: HTTP wrapper does not support writable connections'
			);
			return FALSE;
		}

		// TODO: Find out what needs to happen when the path is already open. I may have misunderstood here.

		$this->isHeadersSet = FALSE;
		$this->url = \parse_url( $pPath );
		// See if we have a save file for this request.
		$localFile = $this->getSaveFile();
		// Load from local cache, or from the network.
		if ( \file_exists($localFile) )
		{
			$this->resource = \fopen( $localFile, 'r' );
		}
		else
		{
			$remoteSocket = 'tcp://' . $this->url[ 'host' ];
			$timeout = ini_get( 'default_socket_timeout' );
			$port = 80;
			// When port is specified, use that.
			if ( \array_key_exists('port', $this->url) )
			{
				$port = ( int ) $this->url[ 'port' ];
			}

			// When context options are set, use them.
			$options = \stream_context_get_options( $this->context );
			$httpOptions = NULL;
			if ( \array_key_exists('http', $options) )
			{
				$httpOptions = $options['http'];
				$timeout = array_key_exists('timeout', $httpOptions) ? $httpOptions['timeout'] : $timeout;
			}

			// Open a socket connection to the server.
			$this->resource = @\fsockopen( $remoteSocket, $port, $errorNo, $errorStr, $timeout );

			// Alert the developer when there is an error connecting.
			if ( !is_resource($this->resource) )
			{
				\trigger_error( 'fsockopen(' . $remoteSocket. '): ' . $errorStr );
				return FALSE;
			}
			// Alert developer of connection error.
			if ($errorNo !== 0 || !empty($errorStr)) {
				\trigger_error( 'error (' . $errorNo . '):' . $errorStr . PHP_EOL );
			}

			// Set timeout.
			\stream_set_timeout($this->resource, $timeout);

			// TODO: figure out when to set blocking mode.
			if ( $pFlags !== 0 ) {
				// TODO: Handle flags.
			}

			$request = $this->buildRequest( $httpOptions );
			// Send the request.
			\fwrite( $this->resource, $request );
		}

		$this->populateResponseHeaders();

		// Indicate that we have successfully opened the path.
		$pOpenedPath = $pPath;

		return TRUE;
	}

	/**
	 * @param int $count
	 * @return string
	 */
	public function stream_read( $count )
	{
		// Get the content
		$content = \fread( $this->resource, $count );
		$this->position += \strlen( $content );
		$this->content .= $content;
		return $content;
	}

	/**
	 * TODO: Find out why this is trigger when using \file_get_contents().
	 *
	 * @return array
	 */
	public function stream_stat()
	{
		$data = [];
		return $data;
	}

	/**
	 * Parse the content for the headers.
	 */
	private function populateResponseHeaders()
	{
		if ( $this->isHeadersSet )
		{
			return;
		}
		while ( !\feof($this->resource) )
		{
			$line = \fgets( $this->resource );
			$this->content .= $line;
			$this->position += \strlen( $line );
			$line = \trim( $line );
			// When you reach the end of the header, then exit the loop.
			if ( empty($line) )
			{
				break;
			}
			$this->wrapperData[] = $line;
		}
	}

	/**
	 * Set where to save raw socket data files.
	 *
	 * @return string
	 */
	static public function getSaveDir()
	{
		return self::$saveDir;
	}

	/**
	 * Get save file name.
	 *
	 * @return string
	 */
	static public function getSaveFilename()
	{
		return self::$saveFile;
	}

	/**
	 * Set directory to save socket data files.
	 *
	 * @param $pDirectory
	 * @return bool
	 */
	static public function setSaveDir( $pDirectory )
	{
		if ( \is_dir($pDirectory) )
		{
			self::$saveDir = \realpath( $pDirectory );
			return TRUE;
		}
		return FALSE;
	}

	/**
	 * Set the file name for the local file.
	 *
	 * @param $pFilename
	 * @return bool
	 */
	static public function setSaveFilename( $pFilename )
	{
		// A filename must not contain the following chars:
		$invalidChars = [ ',', '<', '>', '*', '?', '|', '\\', '/', "'", '"', ':' ];
		// Build regular expression.
		$invalidCharRegExPatter = '@'. implode( $invalidChars ) . '@';

		// Notify the developer when a filename contains invalid characters.
		if ( \preg_match($invalidCharRegExPatter, $pFilename) === 1 )
		{
			\trigger_error( 'A filename cannot contain the following characters: ' . implode('', $invalidChars) );
			return FALSE;
		}

		self::$saveFile = $pFilename;
		return TRUE;
	}

	/**
	 * Build the request according to: http://www.w3.org/Protocols/rfc2616/rfc2616-sec5.html
	 */
	private function buildRequest()
	{
		// template example:
		// -----------------
		// {METHOD} {URI} HTTP-{1.0|1.1}CRLF
		// {headers}CRLF
        // CRLF
        // {message-body}
		$method = 'GET';
		$httpVersion = '1.0';
		$headers = '';
		$body = '';
		$page = ( \array_key_exists('path', $this->url) ) ? $this->url[ 'path' ] : '/';

		$options = \stream_context_get_options( $this->context );
		$httpOptions = [];
		if ( \array_key_exists('http', $options) )
		{
			$httpOptions = $options[ 'http' ];
		}
		// Get method verb.
		if ( \array_key_exists('method', $httpOptions) )
		{
			$method = $httpOptions[ 'method' ];
		}
		// Get content body.
		if ( \array_key_exists('content', $httpOptions) )
		{
			$body = $httpOptions[ 'content' ];
		}
		// Get headers.
		if ( \array_key_exists('header', $httpOptions) )
		{
			$headers = $httpOptions[ 'header' ];
		}
		// Get protocol version.
		if ( \array_key_exists('protocol_version', $httpOptions) )
		{
			$httpVersion = $httpOptions[ 'protocol_version' ];
		}
		// When host header was not set using context for \fopen/\file_get_contents functions,
		// you must manually add the Host: header.
		if (\strpos(strtolower($headers), 'host:') === FALSE) {
			$headers = "host: {$this->url['host']}" . $headers;
		}
		// Build the request as a string.
		$request = \sprintf(
			"%s %s HTTP/%s\r\n%s\r\n\r\n%s",
			$method,
			$page,
			$httpVersion,
			$headers,
			$body
		);

		return $request;
	}
	/**
	 * Get the full file path by generating one from the URL, or the one set by the developer.
	 *
	 * @return bool
	 * @throws InterceptionException
	 */
	private function getSaveFile()
	{
		$filename = self::getSaveFilename();
		// When not set by the developer.
		if ( empty($filename) )
		{
			throw new InterceptionException( 'Please set a filename to save the contents of the request' );
		}
		$ext = '.rsd';
		return self::getSaveDir() . DIRECTORY_SEPARATOR . $filename . $ext;
	}

	/**
	 * Clear the save file name.
	 */
	static public function clearSaveFile()
	{
		self::$saveFile = '';
	}
}
?>