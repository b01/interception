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
	private
		/** @var string Save directory. */
		$saveDir,
		/** @var string */
		$wrapperClass,
		/** @var array */
		$wrappers;

	/**
	 * @param string $pWrapperClass Interception stream wrapper to register.
	 * @param string $pSaveDir Directory where RSD files will be saved.
	 * @throws InterceptionException
	 */
	public function __construct( $pWrapperClass, $pSaveDir = NULL, array $pWrappers = NULL )
	{
		if ( empty($pWrapperClass) )
		{
			throw new InterceptionException(
				'You must set the stream wrapper class as the first argument, leave out the namespace.'
			);
		}
		if ( !\is_dir($pSaveDir) && strcmp($pSaveDir, 'FIXTURES_PATH') !== 0 )
		{
			throw new InterceptionException(
				'You must set the directory where to save files as the second argument.'
			);
		}
		$this->wrapperClass = $pWrapperClass;
		$this->wrappers = $pWrappers;
		// Substitute FIXTURES_PATH it with constant value.
		$this->saveDir = \str_replace(
			'FIXTURES_PATH', constant('FIXTURES_PATH'), $pSaveDir
		);

		// Set which wrapper to replace if not specified.
		if ( $this->wrappers === NULL ) {
			$this->wrappers = array( strtolower($this->wrapperClass) );
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
			\stream_wrapper_restore( 'http' );
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
				'\\Kshabazz\\Interception\\StreamWrappers\\' . $this->wrapperClass,
				\STREAM_IS_URL
			);
		}
		Http::setSaveDir( $this->saveDir );
	}
}
?>