<?php namespace Kshabazz\Interception;
/**
 * Add @interception annotation, and some configuration from PHPUnit XML config.
 */
use Kshabazz\Interception\StreamWrappers\Http;

/**
 * Class InterceptionListener
 *
 * @package \Kshabazz\Interception
 */
class InterceptionListener extends \PHPUnit_Framework_BaseTestListener implements \PHPUnit_Framework_TestListener
{
	static private
		/** @var string Save directory. */
		$saveDir;

	private
		/** @var string */
		$wrapperClass;

	/**
	 * @param string $pWrapper Interception stream wrapper to register.
	 * @param string $pSaveDir Directory where RSD files will be saved.
	 * @throws InterceptionException
	 */
	public function __construct( $pWrapper, $pSaveDir = NULL )
	{
		if ( empty($pWrapper) )
		{
			throw new InterceptionException(
				'You must set the stream wrapper class as the first argument, leave out the namespace.'
			);
		}
		if ( !empty($pSaveDir) )
		{
			self::setSaveDir( $pSaveDir );
		}
		$this->wrapperClass = $pWrapper;
	}

	/**
	 * Restore PHP built-in HTTP stream wrapper and perform any other clean-up.
	 *
	 * @param \PHPUnit_Framework_TestSuite $suite
	 * @return bool
	 */
	public function endTestSuite( \PHPUnit_Framework_TestSuite $suite )
	{
		return \stream_wrapper_restore( 'http' );
	}

	/**
	 * @param \PHPUnit_Framework_Test $test
	 */
	public function startTest( \PHPUnit_Framework_Test $test )
	{
		$annotationKey = 'interception';
		$annotations = $test->getAnnotations();
		if ( \array_key_exists($annotationKey, $annotations['method']) )
		{
			$filename = $annotations[ 'method' ][ $annotationKey ][ 0 ];
			Http::setSaveFilename( $filename );
		}
	}

	/**
	 * @param \PHPUnit_Framework_TestSuite $suite
	 */
	public function startTestSuite( \PHPUnit_Framework_TestSuite $suite )
	{
		$wrapperName = strtolower( $this->wrapperClass );
		\stream_wrapper_unregister( $wrapperName );
		\stream_register_wrapper(
			$wrapperName,
			'\\Kshabazz\\Interception\\StreamWrappers\\' . $this->wrapperClass,
			\STREAM_IS_URL
		);
		Http::setSaveDir( self::getSaveDir() );
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
	 * Set directory to save socket data files.
	 *
	 * @param $pDirectory
	 * @return bool
	 * @throws InterceptionException
	 */
	static public function setSaveDir( $pDirectory )
	{
		if ( !\is_dir($pDirectory) )
		{
			throw new InterceptionException( 'No such directory ' . $pDirectory );
		}
		self::$saveDir = \realpath( $pDirectory );
		return TRUE;
	}
}
?>