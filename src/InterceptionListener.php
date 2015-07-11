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
	const
		STREAM_WRAPPER_NAME_SPACE = '\\Kshabazz\\Interception\\StreamWrappers\\';

	private
		/** @var string Save directory. */
		$saveDir,
		/** @var string */
		$wrapperClass,
		/** @var array */
		$wrappers;

	/**
	 * @param string $pWrapperClass Interception stream wrapper to register.
	 * @param string $pSaveDir A directory that exists, can also be a constant set to a directory.
	 *                         This is where RSD files will be saved.
	 * @throws InterceptionException
	 */
	public function __construct( $pWrapperClass, $pSaveDir = NULL, array $pWrappers = NULL )
	{
		$wrapperClass = self::STREAM_WRAPPER_NAME_SPACE . $pWrapperClass;

		if ( empty($pWrapperClass) || !class_exists($wrapperClass) )
		{
			throw new InterceptionException( InterceptionException::BAD_STREAM_WRAPPER );
		}

		// Use a directory that exists or a constant that is defined to a directory that exists.
		if ( !\is_dir($pSaveDir) )
		{
			if ( defined($pSaveDir) )
			{
				// When path is stored in constant.
				$pSaveDir = constant( $pSaveDir );
				if ( !\is_dir( $pSaveDir ) )
				{
					throw new InterceptionException( InterceptionException::BAD_SAVE_CONST, [$pSaveDir] );
				}
			}
			else
			{
				throw new InterceptionException( InterceptionException::BAD_SAVE_DIR );
			}
		}

		$this->wrapperClass = $pWrapperClass;
		$this->wrappers = $pWrappers;
		$this->saveDir = $pSaveDir;

		// Set which wrapper to replace if not specified.
		if ( $this->wrappers === NULL ) {
			$this->wrappers = [ strtolower($this->wrapperClass) ];
		}
	}

	/**
	 * Restore PHP built-in HTTP stream wrapper and perform any other clean-up.
	 *
	 * @param \PHPUnit_Framework_TestSuite $suite
	 * @return bool
	 */
	public function endTestSuite( \PHPUnit_Framework_TestSuite $suite )
	{
		foreach ( $this->wrappers as $wrapper )
		{
			\stream_wrapper_restore( $wrapper );
		}
		return TRUE;
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
		foreach ( $this->wrappers as $wrapper )
		{
			\stream_wrapper_unregister( $wrapper );
			\stream_register_wrapper(
				$wrapper,
				self::STREAM_WRAPPER_NAME_SPACE . $this->wrapperClass,
				\STREAM_IS_URL
			);
		}
		Http::setSaveDir( $this->saveDir );
	}
}
?>