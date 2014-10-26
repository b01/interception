<?php namespace Kshabazz\Interception;
/**
 * This template was designed from the prototype at http://php.net/manual/en/class.streamwrapper.php.
 */

/**
 * Class StreamWrapper
 *
 * @package Kshabazz\Interception
 */
class StreamWrapper
{
	/**
	 * Constructor.
	 */
	public function __construct()
	{
	}

	/**
	 * Destructor
	 */
	public function __destruct()
	{
	}

	/**
	 * @return bool
	 */
	public function dir_closedir()
	{
		return FALSE;
	}

	/**
	 * @param string $path
	 * @param int $options
	 * @return bool
	 */
	public function dir_opendir( $path, $options )
	{
		return FALSE;
	}

	/**
	 * @return string
	 */
	public function dir_readdir()
	{
	}

	/**
	 * @return bool
	 */
	public function dir_rewinddir()
	{
		return FALSE;
	}

	/**
	 * @param string $path
	 * @param int $mode
	 * @param int $options
	 * @return bool
	 */
	public function mkdir( $path, $mode, $options )
	{
		return FALSE;
	}

	/**
	 * @param string $path_from
	 * @param string $path_to
	 * @return bool
	 */
	public function rename( $path_from, $path_to )
	{
	}

	/**
	 * @param string $path
	 * @param int $options
	 * @return bool
	 */
	public function rmdir( $path, $options )
	{
	}

	/**
	 * @param int $cast_as
	 * @return resource
	 */
	public function stream_cast( $cast_as )
	{
	}

	/**
	 * @return void
	 */
	public function stream_close()
	{
	}

	/**
	 * @return bool
	 */
	public function stream_eof()
	{
		return FALSE;
	}

	/**
	 * @return bool
	 */
	public function stream_flush()
	{
		return FALSE;
	}

	/**
	 * @param int $operation
	 * @return bool
	 */
	public function stream_lock( $operation )
	{
		return FALSE;
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
	 * @param string $path
	 * @param string $mode
	 * @param int $options
	 * @param string $opened_path
	 * @return bool
	 */
	public function stream_open( $path, $mode, $options, &$opened_path )
	{
		return FALSE;
	}

	/**
	 * @param int $count
	 * @return string
	 */
	public function stream_read( $count )
	{
	}

	/**
	 * @param int $offset
	 * @param int $whence
	 * @return bool
	 */
	public function stream_seek( $offset, $whence = SEEK_SET )
	{
		return FALSE;
	}

	/**
	 */
	/**
	 * @param int $option
	 * @param int $arg1
	 * @param int $arg2
	 * @return bool
	 */
	public function stream_set_option( $option, $arg1, $arg2 )
	{
		return FALSE;
	}

	/**
	 * @return array
	 */
	public function stream_stat()
	{
	}

	/**
	 * @return int
	 */
	public function stream_tell()
	{
	}

	/**
	 * @param int $new_size
	 * @return bool
	 */
	public function stream_truncate( $new_size )
	{
		return FALSE;
	}

	/**
	 * @param string $data
	 * @return int
	 */
	public function stream_write( $data )
	{
	}

	/**
	 * @param string $path
	 * @return bool
	 */
	public function unlink( $path )
	{
		return FALSE;
	}

	/**
	 * @param string $path
	 * @param int $flags
	 * @return array
	 */
	public function url_stat( $path, $flags )
	{
	}
}
?>