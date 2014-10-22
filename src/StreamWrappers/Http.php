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
	public
		/** @var resource */
		$context,
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
		$areHeadersSet,
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
		$this->content = NULL;
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
			$saveFile = $this->getSaveFile( $this->url[ 'host' ] );
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
			\trigger_error( 'Only read mode is supported.' );
			return FALSE;
		}

		// TODO: Find out what needs to happen when the path is already open.
		$this->areHeadersSet = FALSE;
		$this->url = \parse_url( $pPath );
		// See if we have a save file for this request.
		$localFile = $this->getSaveFile( $this->url[ 'host' ] );
		// Load from local cache, or from the network.
		if ( \file_exists($localFile) )
		{
			$this->resource = \fopen( $localFile, 'r' );
		}
		else
		{
			$remoteSocket = 'tcp://' . $this->url[ 'host' ];
			$this->resource = @\fsockopen( $remoteSocket, 80, $errorNo, $errorStr );
			// Alert the developer when there is an error connecting.
			if ( $this->resource === FALSE )
			{
				\trigger_error( 'Unable to connect to ' . $this->url['host'] . "\nReason: " . $errorStr );
				return FALSE;
			}
			$page = ( \array_key_exists('path', $this->url) ) ? $this->url[ 'path' ] : '/';
			$headers = \sprintf( "GET %s HTTP/1.0\r\nHost: %s\r\n\r\n", $page, $this->url['host'] );
			// Setup the context so we can read from the socket.
			\fwrite( $this->resource, $headers );
		}

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
		$this->populateResponseHeaders( $content );
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
		if ( $this->areHeadersSet )
		{
			return;
		}
		$headersStr = \strstr( $this->content, "\r\n\r\n", TRUE );
		if ( !empty($headersStr) > 0 )
		{
			$this->wrapperData = \explode( "\r\n", $headersStr );
			$this->areHeadersSet = TRUE;
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
	static private function clearSaveFile()
	{
		self::$saveFile = '';
	}
}
?>