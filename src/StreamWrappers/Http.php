<?php namespace Kshabazz\Interception\StreamWrappers;
/**
 * Intercept HTTP request when \fopen or \file_get_contents is called.
 */

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
			$saveFile = $this->getSaveFile( $this->url['host'] );
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
	 * @param int $pOptions Flags
	 * @param string $pOpenedPath
	 * @return bool
	 * @throws \Exception
	 */
	public function stream_open( $pPath, $pMode, $pOptions, &$pOpenedPath )
	{
		if( 'r' !== $pMode && 'rb' !== $pMode )
		{
			\trigger_error(
				'fopen(' . $pPath . '): failed to open stream: HTTP wrapper does not support writeable connections'
			);
			return FALSE;
		}

		// TODO: Find out what needs to happen when the path is already open.
		$this->isHeadersSet = FALSE;
		$this->url = \parse_url( $pPath );
		// See if we have a save file for this request.
		$localFile = $this->getSaveFile( $this->url[ 'host' ] );
		// Load from local cache, or from the network.
		if ( \file_exists($localFile) )
		{
			$this->resourceType = self::RESOURCE_TYPE_FILE;
			$this->resource = \fopen( $localFile, 'r' );
		}
		else
		{
			$this->resourceType = self::RESOURCE_TYPE_STREAM;
			$remoteSocket = 'tcp://' . $this->url[ 'host' ];
			$this->resource = @\fsockopen( $remoteSocket, 80, $errorNo, $errorStr );
			// Alert the developer when there is an error connecting.
			if ( $this->resource === FALSE )
			{
				// Original built-in HTTP wrapper error:
				//  'fopen(): php_network_getaddresses: getaddrinfo failed: No such host is known.
				\trigger_error( 'fsockopen(' . $remoteSocket. '): ' . $errorStr );
				return FALSE;
			}
			$page = ( \array_key_exists('path', $this->url) ) ? $this->url[ 'path' ] : '/';
			// TODO: Allow developer to set headers.
			$headers = \sprintf( "GET %s HTTP/1.0\r\nHost: %s\r\n\r\n", $page, $this->url['host'] );
			// Setup the context so we can read from the socket.
			\fwrite( $this->resource, $headers );
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
		$this->position += strlen( $content );
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
			$this->position += strlen( $line );
			$line = trim( $line );
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
	 * Get the full file path by generating one from the URL, or the one set by the developer.
	 *
	 * @param string $pUrl
	 * @return bool
	 */
	private function getSaveFile( $pUrl )
	{
		$filename = self::getSaveFilename();
		// When not set by the developer.
		if ( empty($filename) )
		{
			$filename = \str_replace( '.', '-', $pUrl );
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