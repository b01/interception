<?php namespace Kshabazz\Interception\StreamWrappers;
/**
 * Intercept HTTP request when \fopen or \file_get_contents is called.
 */

/**
 * Class Http
 *
 * @package Kshabazz\Interception\StreamWrappers
 */
class Http
{
	const
		/** @var int Resource type */
		TYPE_FILE = 2,
		/** @var int Resource type */
		TYPE_SOCKET = 2;

	/** @var resource */
	public $context;

	public static
		/** @var string Directory to save raw files. */
		$rawSaveDir = '.';

	private
		/** @var string */
		$content,
		/** @var resource */
		$resource,
		/** @Var int */
		$type,
		/** @var string */
		$url;

	/**
	 * Constructor
	 */
	public function __construct()
	{
		$this->content = NULL;
		$this->resource = NULL;
		$this->tyep = NULL;
		$this->url = NULL;
	}

	/**
	 * Close the resource.
	 *
	 * @return void
	 */
	public function stream_close()
	{
		if ( \is_resource($this->resource) )
		{
			\fclose( $this->resource );
			if ( !empty($this->content) )
			{
				$saveFile = $this->convertUrlToFileName( $this->url );
				file_put_contents( $saveFile, $this->content );
			}
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
	 * @param string $path
	 * @param int $option
	 * @param mixed $value
	 * @return mixed
	 */
	public function stream_metadata( $path, $option, $value )
	{

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

		$this->url = \parse_url( $pPath );

		// See if we have a save file for this request.
		$rawFile = $this->convertUrlToFileName( $this->url );

		if ( \file_exists($rawFile) )
		{
			$this->type = self::TYPE_FILE;
			$this->resource = \fopen( $rawFile, 'r' );
		}
		else
		{
			$this->type = self::TYPE_SOCKET;
			$remoteSocket = 'tcp://' . $this->url[ 'host' ];
			$this->resource = \fsockopen( $remoteSocket, 80, $errorNo, $errorStr );

			if ( $this->resource === FALSE )
			{
				throw new \Exception( 'Unable to connect to ' . $this->url['host'] );
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
		$this->content .= $content;
		return $content;
	}

	/**
	 * @return array
	 */
	public function stream_stat()
	{
		$data = [];
		return $data;
	}

	/**
	 * Set where to save raw socket data files.
	 *
	 * @return string
	 */
	public static function getSaveDir()
	{
		return self::$rawSaveDir;
	}

	/**
	 * Set where to save raw socket data files.
	 *
	 * @param $pDirectory
	 * @return bool
	 */
	public static function setSaveDir( $pDirectory )
	{
		if ( \is_dir($pDirectory) )
		{
			self::$rawSaveDir = \realpath( $pDirectory );
			return TRUE;
		}
		return FALSE;
	}

	/**
	 * Verify if the URL being request has already been save on disk.
	 *
	 * @param array $pParseUrl
	 * @return bool
	 */
	private function convertUrlToFileName( array $pParseUrl )
	{
		$fileName = \str_replace('.', '-', $pParseUrl['host'] );
		$ext = '.rsd';
		return self::getSaveDir() . DIRECTORY_SEPARATOR . $fileName . $ext;
	}
}
?>