<?php namespace Kshabazz\Tests\Interception;

use \Kshabazz\Interception\InterceptionListener;

/**
 * Class InterceptionListenerTest
 *
 * @package \Kshabazz\Tests\Interception
 */
class InterceptionListenerTest extends \PHPUnit_Framework_TestCase
{
	/**
	 * @interception ignore-annotation-test
	 */
	public function test_interception_annotation()
	{
		$listener = new InterceptionListener( 'Http', FIXTURES_PATH );
		$suite = new \PHPUnit_Framework_TestSuite();
		$listener->startTestSuite( $suite );
		$listener->startTest( $this );
		$handle = \fopen( 'http://www.example.com/', 'r' );
		\fclose( $handle );
		$filename = FIXTURES_PATH . DIRECTORY_SEPARATOR . 'ignore-annotation-test.rsd';
		$this->assertTrue( \file_exists($filename) );
	}

	/**
	 * @expectedException \Kshabazz\Interception\InterceptionException
	 * @expectedExceptionMessage You must set the stream wrapper class as the first argument, leave out the namespace.
	 */
	public function test_no_stream_wrapper_class()
	{
		( new InterceptionListener(NULL, NULL) );
	}

	public function test_tearDown()
	{
		$listener = new InterceptionListener( 'Http', FIXTURES_PATH );
		$unregistered = $listener->endTestSuite( new \PHPUnit_Framework_TestSuite() );
		$this->assertTrue( $unregistered );
	}

	/**
	 * @expectedException \Kshabazz\Interception\InterceptionException
	 * @expectedExceptionMessage Second argument must be a directory where to save RSD files
	 */
	public function test_setting_save_invlaid_directory()
	{
		( new InterceptionListener('Http', NULL) );
	}
}
?>