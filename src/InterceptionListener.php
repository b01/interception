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
class InterceptionListener extends \PHPUnit_Framework_BaseTestListener
{
	private
		/** @var string Annotation key to flag all request be intercepted. */
		$multiIntercept,
		/** @var string Save directory. */
		$saveDir,
		/** @var string Annotation key for to flag a single request be intercepted. */
		$singleIntercept,
		/** @var string */
		$wrapperClass,
		/** @var array */
		$wrappers;

	/**
	 * @param string $pWrapperClass Interception stream wrapper to register.
	 * @param string $pSaveDir A directory that exists, can also be a constant set to a directory.
	 *                         This is where RSD files will be saved.
	 * @param array $pWrappers Wrapper to intercept.
	 * @throws InterceptionException
	 */
	public function __construct( $pWrapperClass, $pSaveDir, array $pWrappers)
	{
		$wrapperClass = $pWrapperClass;

		if ( empty($pWrapperClass) || !\class_exists($wrapperClass) )
		{
			throw new InterceptionException( InterceptionException::BAD_STREAM_WRAPPER );
		}

		// Use a directory that exists or a constant that is defined to a directory that exists.
		// When the save directory is not a path.
		if ( !\is_dir($pSaveDir) )
		{
			// nor a constant.
			if ( !\defined($pSaveDir) )
			{
				throw new InterceptionException( InterceptionException::BAD_SAVE_DIR );
			}
			// Attempt to extract the path stored in constant.
			$pSaveDir = \constant( $pSaveDir );
			// When the constant did not contain a valid path.
			if ( !\is_dir( $pSaveDir ) )
			{
				throw new InterceptionException( InterceptionException::BAD_SAVE_CONST, [$pSaveDir] );
			}
		}

		$this->wrapperClass = $pWrapperClass;
		$this->wrappers = $pWrappers;
		$this->saveDir = $pSaveDir;
		$this->multiIntercept = 'interceptions';
		$this->singleIntercept = 'interception';
	}


	public function endTest( \PHPUnit_Framework_Test $test, $time )
	{
		$annotations = $test->getAnnotations();
		// Intercept a single request per test.
		if ( \array_key_exists($this->singleIntercept, $annotations['method']) )
		{
			Http::clearSaveFile();
		}
		// Intercept multiple request per test.
		if ( \array_key_exists($this->multiIntercept, $annotations['method']) )
		{
			Http::clearPersistSaveFile();
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
		$annotations = $test->getAnnotations();
		// Intercept a single request per test.
		if ( \array_key_exists($this->singleIntercept, $annotations['method']) )
		{
			$filename = $annotations[ 'method' ][ $this->singleIntercept ][ 0 ];
			Http::setSaveFilename( $filename );
		}
		// Intercept multiple request per test.
		if ( \array_key_exists($this->multiIntercept, $annotations['method']) )
		{
			$filename = $annotations[ 'method' ][ $this->multiIntercept ][ 0 ];
			Http::persistSaveFile( $filename );
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
				$this->wrapperClass,
				\STREAM_IS_URL
			);
		}
		Http::setSaveDir( $this->saveDir );
	}
}
?>