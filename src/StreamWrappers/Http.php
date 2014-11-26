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
class Http implements \ArrayAccess, \Countable
{
	const
		/** @var int */
		RESOURCE_TYPE_FILE = 1,
		/** @var int */
		RESOURCE_TYPE_SOCKET = 2;

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
		/** @var resource */
		$resource,
		/** @var int */
		$resourceType,
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
		$this->resourceType = NULL;
		$this->url = NULL;
		$this->wrapperData = NULL;
	}

	/**
	 * @inherit
	 */
	public function count()
	{
		return \count( $this->wrapperData );
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
	 * @inherit
	 */
	public function offsetGet( $pKey )
	{
		return $this->wrapperData[ $pKey ];
	}

	/**
	 * @inherit
	 * @codeCoverageIgnore
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

		// Close the resource.
		if ( $this->resourceType === self::RESOURCE_TYPE_FILE )
		{
			\fclose( $this->resource );
		}
		else if ( $this->resourceType === self::RESOURCE_TYPE_SOCKET )
		{
			try
			{
				// This could throw errors if the socket is not connected on some systems.
				\socket_shutdown( $this->resource );
			}
			catch ( \Exception $pError )
			{
			}
			\socket_close( $this->resource );
			// Only save the file when not loaded locally.
			$saveFile = $this->getSaveFile();
			\file_put_contents( $saveFile, $this->content );
		}

		// Reset so we do not overwrite a file unintentionally for the next request.
		self::clearSaveFile();
	}

	/**
	 * @return bool
	 */
	public function stream_eof()
	{
		// Start off assuming the EOF has been reached.
		$eof = TRUE;


		if ( $this->resourceType === self::RESOURCE_TYPE_FILE )
		{
			$eof = \feof( $this->resource );
		}
		else if ( $this->resourceType === self::RESOURCE_TYPE_SOCKET )
		{
			$bytes = \socket_recv( $this->resource, $buffer, 1, \MSG_PEEK );
			if ( $bytes === FALSE )
			{
				$this->triggerSocketError();
			}
			$eof = $bytes === 0;
		}

		return $eof;
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
				 $pPath . ': failed to open stream: HTTP wrapper does not support writable connections'
			);
			return FALSE;
		}

		// TODO: Find out what needs to happen when the path is already open. I may have misunderstood here.

		$this->url = \parse_url( $pPath );
		// See if we have a save file for this request.
		$localFile = $this->getSaveFile();
		// Load from local cache, or from the network.
		if ( \file_exists($localFile) )
		{
			$this->resourceType = self::RESOURCE_TYPE_FILE;
			$this->resource = \fopen( $localFile, 'r' );
		}
		else
		{
			$this->resourceType = self::RESOURCE_TYPE_SOCKET;
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

			// Init socket resource.
			$this->resource = \socket_create(\AF_INET, \SOCK_STREAM, \SOL_TCP);
			if ( !is_resource($this->resource) )
			{
				\trigger_error( 'Unable to connect to ' . $this->url['host'] );
				return FALSE;
			}

			// Attempt to connect.
			$isConnected = @\socket_connect( $this->resource, $this->url['host'], $port );
			if ( !$isConnected )
			{
				$this->triggerSocketError();
				return FALSE;
			}
			// TODO: figure out when to set blocking mode.
			if ( $pFlags !== 0 ) {
				// TODO: Handle flags.
			}

			$request = $this->buildRequest( $httpOptions );
			$lengthToWrite = \strlen($request);
			$lengthWritten = 0;
			// Send the request.
			do
			{
				$lengthWritten += \socket_write( $this->resource, $request );
				if ( $lengthWritten >= $lengthToWrite ) {
					break;
				}
				else
				{
					echo( 'still need to write this much to the socket: ' . ($lengthToWrite - $lengthWritten) . "\n" );
				}

			} while ( $lengthWritten < $lengthToWrite );
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
		$content = $this->readFromResource( $count );

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
		return [];
	}

	/**
	 * Clear the save file name.
	 */
	static public function clearSaveFile()
	{
		self::$saveFile = '';
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
		if ( \strpos(strtolower($headers), 'host:') === FALSE )
		{
			$lineBreak = ( \strlen($headers) > 0 ) ? "\r\n" : '';
			$headers = "host: {$this->url['host']}{$lineBreak}{$headers}";
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
	 * Parse the content for the headers.
	 */
	private function populateResponseHeaders()
	{
		$done = FALSE;
		$line = NULL;
		$header = '';
		$buffer = NULL;

		// When using a file stream.
		if ( $this->resourceType === self::RESOURCE_TYPE_FILE ) {
			$done = \feof( $this->resource );
		}

		// Read the headers from the resource.
		while ( !$done )
		{
			$buffer = $this->readFromResource(1);
			// Update stream
			$this->content .= $buffer;
			$header = \strstr( $this->content, "\r\n\r\n", TRUE );
			// When you reach the end of the header, then exit the loop.
			if ( $buffer === '' || $header !== FALSE )
			{
				break;
			}
		}

		// Update cursor position.
		$this->position += \strlen( $this->content );
		// Parse header.
		if ( strlen(trim($header)) > 0 )
		{
			// Set header
			$this->wrapperData = explode( "\r\n", $header );
		}
	}

	/**
	 * @param resource $pResource
	 * @param int $pLength
	 * @return bool
	 */
	private function readFromSocket( $pResource, $pLength = 100 )
	{
		$reads = array( $pResource );
		$writes = NULL;
		$excepts = NULL;
		$returnValue = FALSE;
		if ( FALSE === ($changedStreams = \socket_select($reads, $writes, $excepts, 1, 2)) )
		{
			$this->triggerSocketError();
		}
		else if ( $changedStreams > 0 )
		{
			$bytes = \socket_recv( $reads[0], $buffer, $pLength, \MSG_WAITALL );
			// When an error occurs.
			if ( $bytes === FALSE )
			{
				$this->triggerSocketError();
			}
			// When there is more data, then return the data.
			if ( $bytes > 0 )
			{
				$returnValue = $buffer;
			}
		}
		return $returnValue;
	}

	/**
	 * @param int $pCount
	 * @return bool|string
	 */
	private function readFromResource( $pCount = 100 )
	{
		if ( $this->resourceType === self::RESOURCE_TYPE_FILE )
		{
			$buffer = \fread( $this->resource, $pCount );
			return $buffer;
		}
		else if ( $this->resourceType === self::RESOURCE_TYPE_SOCKET )
		{
			$buffer = $this->readFromSocket( $this->resource, $pCount );
			return $buffer;
		}
		return FALSE;
	}

	/**
	 * Trigger socket error.
	 */
	private function triggerSocketError()
	{
		$erroNo = socket_last_error( $this->resource );
		$errorStr = \socket_strerror( $erroNo );
		trigger_error( '\\Kshabazz\\Interception\\StreamWrappers\\Http ' . $errorStr );
	}
}
?>