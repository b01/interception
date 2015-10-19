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

	static private
		/** @var int Increment file when $saveFilePersist is true. */
		$persistFileIncrement = 0,
		/** @var string Directory to save raw files. */
		$saveDir = NULL,
		/** @var string */
		$saveFile = '',
		/** @var bool Prevent the save file from being cleared on close. */
		$saveFilePersist = FALSE;

	private
		/** @var string */
		$content,
		/** @var resource */
		$resource,
		/** @var int */
		$resourceType,
		/** @var bool */
		$ssl,
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
		$this->ssl = FALSE;
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
		if ( $this->resourceType === static::RESOURCE_TYPE_FILE )
		{
			\fclose( $this->resource );
		}
		else if ( $this->ssl )
		{
			\fclose( $this->resource );
			// Only save the file when not loaded locally.
			$saveFile = $this->getSaveFile();
			\file_put_contents( $saveFile, $this->content );
		}
		else if ( $this->resourceType === static::RESOURCE_TYPE_SOCKET )
		{
			// This could throw errors if the socket is not connected on some systems.
			@\socket_shutdown( $this->resource );
			\socket_close( $this->resource );
			// Only save the file when not loaded locally.
			$saveFile = $this->getSaveFile();
			\file_put_contents( $saveFile, $this->content );
		}

		// Leave the resource for garbage clean-up.
		$this->resource = NULL;

		// Reset so we do not overwrite a file unintentionally for the next request.
		static::clearSaveFile();
	}

	/**
	 * @return bool
	 */
	public function stream_eof()
	{
		// Start off assuming the EOF has been reached.
		$eof = TRUE;

		if ( $this->ssl || $this->resourceType === static::RESOURCE_TYPE_FILE )
		{
			$eof = \feof( $this->resource );
		}
		else if ( $this->resourceType === static::RESOURCE_TYPE_SOCKET )
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
		//
		if ( static::$saveFilePersist )
		{
			++static::$persistFileIncrement;
		}
		// See if we have a save file for this request.
		$localFile = $this->getSaveFile();
		// Load from local file, or make an internet request.
		if ( \file_exists($localFile) )
		{
			$this->resourceType = static::RESOURCE_TYPE_FILE;
			$this->resource = \fopen( $localFile, 'r' );
		}
		else
		{
			$this->resourceType = static::RESOURCE_TYPE_SOCKET;
			$timeout = ini_get( 'default_socket_timeout' );
			$this->ssl = ( \strcmp($this->url['scheme'], 'https') === 0 );
			$port = ( $this->ssl ) ? 443 : 80;
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

			if ( $this->ssl ) // HTTPS
			{
				$remoteSocket = sprintf('ssl://%s:%d', $this->url['host'], $port );
				$this->resource = \stream_socket_client(
					$remoteSocket,
					$errorNo,
					$errorStr,
					$timeout,
					( $pFlags ?: \STREAM_CLIENT_CONNECT ),
					$this->context
				);

				if ( $errorNo > 0 ) {
					\trigger_error($errorStr);
				}
			}
			else // HTTP
			{
				// Init socket resource.
				$this->resource = \socket_create( \AF_INET, \SOCK_STREAM, \SOL_TCP );
				if ( !is_resource( $this->resource ) )
				{
					\trigger_error( 'Unable to connect to ' . $this->url[ 'host' ] );
					return FALSE;
				}

				// Attempt to connect.
				$isConnected = @\socket_connect( $this->resource, $this->url[ 'host' ], $port );
				if ( !$isConnected )
				{
					$this->triggerSocketError();
					return FALSE;
				}
			}

			// TODO: figure out when to set blocking mode.
			if ( $pFlags !== 0 ) {
				// TODO: Handle flags.
			}

			$request = $this->buildRequest( $httpOptions );
			$lengthToWrite = \strlen( $request );
			$lengthWritten = 0;
			// Send the request.
			do
			{
				if ( $this->ssl )
				{
					$lengthWritten += \fputs( $this->resource, $request );
				}
				else
				{
					$lengthWritten += \socket_write( $this->resource, $request );
				}

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
	 * Clear the persist save filename.
	 *
	 * @return bool TRUE when save file is cleared.
	 */
	static public function clearPersistSaveFile()
	{
		static::$saveFilePersist = FALSE;
		static::$persistFileIncrement = 0;

		return static::clearSaveFile();
	}

	/**
	 * Clear the save file name.
	 *
	 * @return bool TRUE when save file is cleared, FALSE when $saveFilePersist is TRUE.
	 */
	static public function clearSaveFile()
	{
		// Clear the save file, unless explicitly told not to.
		if ( !static::$saveFilePersist )
		{
			static::$saveFile = '';
			return TRUE;
		}

		return FALSE;
	}

	/**
	 * Get directory where to save raw socket data files.
	 *
	 * @return string
	 * @throws \Kshabazz\Interception\InterceptionException
	 */
	static public function getSaveDir()
	{
		// When not set.
		if ( !\is_dir(static::$saveDir) )
		{
			throw new InterceptionException( 'Please set a directory to save the request files.' );
		}

		return static::$saveDir;
	}

	/**
	 * Get save file name.
	 *
	 * @return string
	 */
	static public function getSaveFilename()
	{
		return static::$saveFile;
	}

	/**
	 * Allow the save file name to persist, until called with FALSE.
	 *
	 * @param string $pPersistFilename
	 * @return bool Current setting.
	 */
	static public function persistSaveFile( $pPersistFilename )
	{
		static::$saveFilePersist = static::setSaveFilename( $pPersistFilename );
		return static::$saveFilePersist;
	}

	/**
	 * Add a suffix to the file to prevent overwriting when persisting save file.
	 *
	 * @return string
	 */
	static public function persistSuffix()
	{
		$suffix = '';
		if ( static::$saveFilePersist )
		{
			$suffix = '-' . static::$persistFileIncrement;
		}
		return $suffix;
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
			static::$saveDir = \realpath( $pDirectory );
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
		if ( static::isValidFilename($pFilename) )
		{
			static::$saveFile = $pFilename;
			return TRUE;
		}

		return FALSE;
	}

	/**
	 * Validate a name filename.
	 *
	 * @param $pFilename
	 * @return bool
	 */
	static private function isValidFilename( $pFilename )
	{
		// A filename must not contain the following chars:
		$invalidChars = [ ',', '<', '>', '*', '?', '|', '\\', '/', "'", '"', ':' ];
		// Build regular expression.
		$invalidCharRegExPatter = '@'. implode( $invalidChars ) . '@';
		// Notify the developer when a filename contains invalid characters.
		if ( \preg_match($invalidCharRegExPatter, $pFilename) === 1 )
		{
			\trigger_error( 'A filename cannot contain the following characters: ' . implode('', $invalidChars) );
			// When trigger errors are silenced.
			return FALSE;
		}
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
		if ( \array_key_exists('query', $this->url) )
		{
			$page = $page . '?' . $this->url[ 'query' ];
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
		$filename = static::getSaveFilename() . static::persistSuffix();
		// When not set.
		if ( empty($filename) )
		{
			throw new InterceptionException( InterceptionException::NO_FILENAME );
		}
		$ext = '.rsd';

		// Build file path.
		return static::getSaveDir() . DIRECTORY_SEPARATOR . $filename . $ext;
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
		if ( $this->resourceType === static::RESOURCE_TYPE_FILE ) {
			$done = \feof( $this->resource );
		}

		// Read the headers from the resource.
		while ( !$done )
		{
			$buffer = $this->readFromResource( 1 );
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
		if ( $this->ssl || $this->resourceType === static::RESOURCE_TYPE_FILE )
		{
			$buffer = \fread( $this->resource, $pCount );
			return $buffer;
		}
		else if ( $this->resourceType === static::RESOURCE_TYPE_SOCKET )
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
		$errorNo = \socket_last_error( $this->resource );
		$errorStr = \socket_strerror( $errorNo );
		\trigger_error( '\\Kshabazz\\Interception\\StreamWrappers\\Http ' . $errorStr );
	}
}
?>