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
		$listener = new InterceptionListener( 'Http', './fixtures' );
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
		$listener = new InterceptionListener( NULL, './fixtures' );
	}

	/**
	 * @expectedException \Kshabazz\Interception\InterceptionException
	 * @expectedExceptionMessage You must set the path where the stream wrapper class can save files as the second argument.
	 */
	public function test_no_save_path()
	{
		$listener = new InterceptionListener( 'Http', NULL );
	}

	public function test_tearDown()
	{
		$listener = new InterceptionListener( 'Http', './fixtures' );
		$unregistered = $listener->endTestSuite( new \PHPUnit_Framework_TestSuite() );
		$this->assertTrue( $unregistered );
	}
}
?>