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
		/** @var string save path. */
		$savePath,
		/** @var string */
		$wrapperClass;

	/**
	 * @param string $pWrapper Interception stream wrapper to register.
	 * @param string $pSavePath
	 * @throws InterceptionException
	 */
	public function __construct( $pWrapper, $pSavePath )
	{
		if ( empty($pWrapper) )
		{
			throw new InterceptionException(
				'You must set the stream wrapper class as the first argument, leave out the namespace.'
			);
		}
		if ( empty($pSavePath) )
		{
			throw new InterceptionException(
				'You must set the path where the stream wrapper class can save files as the second argument.'
			);
		}
		$this->wrapperClass = $pWrapper;
		$this->savePath = $pSavePath;
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
		// TODO: Get @intercept filename from doc-block.
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
		\stream_wrapper_unregister( 'http' );
		\stream_register_wrapper(
			'http',
			'\\Kshabazz\\Interception\\StreamWrappers\\Http',
			\STREAM_IS_URL
		);
		Http::setSaveDir( $this->savePath );
	}
}
?>