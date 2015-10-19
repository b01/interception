<?php namespace Kshabazz\Tests\Interception;

use \Kshabazz\Interception\InterceptionListener;
use Kshabazz\Interception\StreamWrappers\Http;

/**
 * Class InterceptionListenerTest
 *
 * @package \Kshabazz\Tests\Interception
 * @coversDefaultClass \Kshabazz\Interception\InterceptionListener
 */
class InterceptionListenerTest extends \PHPUnit_Framework_TestCase
{
	/** @var string */
	private $fixtureDir;

	/** @var \PHPUnit_Framework_TestSuite */
	private $suite;

	public function setUp()
	{
		$this->fixtureDir = \FIXTURES_PATH;
		$this->suite = new \PHPUnit_Framework_TestSuite();
	}

	/**
	 * @interception ignore-annotation-test
	 * @covers ::__construct
	 */
	public function test_initialization()
	{
		$listener = new InterceptionListener( HTTP_STREAM_WRAPPER, $this->fixtureDir, ['http'] );
		$this->assertInstanceOf( '\\Kshabazz\\Interception\\InterceptionListener', $listener );
	}

	/**
	 * @interception ignore-annotation-test
	 * @covers ::startTest
	 */
	public function test_interception_used_annotation_for_filename()
	{
		$listener = new InterceptionListener( HTTP_STREAM_WRAPPER, $this->fixtureDir, ['http'] );
		$listener->startTestSuite( $this->suite );
		$listener->startTest( $this );
		$this->assertEquals( 'ignore-annotation-test', Http::getSaveFilename() );
	}

	/**
	 * @expectedException \Kshabazz\Interception\InterceptionException
	 * @expectedExceptionMessage You must set the stream wrapper class as the first argument.
	 * @covers ::__construct
	 */
	public function test_no_stream_wrapper_class()
	{
		( new InterceptionListener(NULL, NULL, ['http']) );
	}

	/**
	 * @covers ::endTestSuite
	 */
	public function test_endTestSuite()
	{
		$listener = new InterceptionListener( HTTP_STREAM_WRAPPER, $this->fixtureDir, ['http'] );
		$unregistered = $listener->endTestSuite( new \PHPUnit_Framework_TestSuite() );
		$this->assertTrue( $unregistered );
	}

	/**
	 * @expectedException \Kshabazz\Interception\InterceptionException
	 * @expectedExceptionMessage Second argument must be a directory where to save RSD files
	 * @covers ::__construct
	 */
	public function test_setting_save_invalid_directory()
	{
		( new InterceptionListener( HTTP_STREAM_WRAPPER, NULL, ['http']) );
	}

	/**
	 * @expectedException \Kshabazz\Interception\InterceptionException
	 * @expectedExceptionMessage Constant "bad directory"
	 * @covers ::__construct
	 */
	public function test_bad_constant_for_path()
	{
		define( 'BAD_PATH', 'bad directory' );
		( new InterceptionListener( HTTP_STREAM_WRAPPER, 'BAD_PATH', ['http']) );
	}

	/**
	 * @interception xml-rss-feed
	 * @covers ::startTestSuite
	 * @covers ::startTest
	 */
	public function test_loading_xml_with_file_get_contents()
	{
		$listener = new InterceptionListener( HTTP_STREAM_WRAPPER, 'FIXTURES_PATH', ['http'] );

		// Get the listener to register the interception HTTP stream wrapper.
		$listener->startTestSuite( $this->suite );

		// Now get the listener to set the save file from the annotation on this test.
		$listener->startTest( $this );

		// Now lets see how the stream wrapper XML.
		$output = \file_get_contents(
			'http://www.quickenloans.com/blog/category/mortgage/mortgage-basics/feed'
		);

		$fixture = $this->fixtureDir . DIRECTORY_SEPARATOR . 'xml-rss-feed.rsd';

		$this->assertFileExists( $fixture );
	}

	/**
	 * @interception xml-rss-feed
	 * @covers ::startTestSuite
	 * @covers ::startTest
	 * @covers ::endTest
	 */
	public function test_xml()
	{
		$listener = new InterceptionListener( HTTP_STREAM_WRAPPER, 'FIXTURES_PATH', ['http'] );

		// Get the listener to register the interception HTTP stream wrapper.
		$listener->startTestSuite( $this->suite );

		// Now get the listener to set the save file from the annotation on this test.
		$listener->startTest( $this );

		// Now lets see how the stream wrapper XML.
		$output = \file_get_contents(
			'http://www.quickenloans.com/blog/category/mortgage/mortgage-basics/feed'
		);

		// Clean up
		$listener->endTest( $this, NULL );
		$listener->endTestSuite( $this->suite  );

		$fixture = $this->fixtureDir . DIRECTORY_SEPARATOR . 'xml-rss-feed.rsd';

		$this->assertFileExists( $fixture );
	}

	/**
	 * @interceptions google
	 * @covers ::startTestSuite
	 * @covers ::startTest
	 * @covers ::endTest
	 */
	public function test_multiple_request()
	{
		$listener = new InterceptionListener( HTTP_STREAM_WRAPPER, 'FIXTURES_PATH', ['http'] );

		// Get the listener to register the interception HTTP stream wrapper.
		$listener->startTestSuite( $this->suite );

		// Now get the listener to set the save file from the annotation on this test.
		$listener->startTest( $this );

		// Now lets see how the stream wrapper XML.
		$output = \file_get_contents( 'http://www.google.com/' );
		$output = \file_get_contents( 'http://www.google.com/' );

		// Clean up
		$listener->endTest( $this, NULL );
		$listener->endTestSuite( $this->suite  );

		$fixture = $this->fixtureDir . DIRECTORY_SEPARATOR . 'google-';

		$this->assertFileExists( $fixture . '1.rsd');
		$this->assertFileExists( $fixture . '2.rsd');
	}

	/**
	 * @interception test-1
	 */
	public function test_part_1_filename_not_cleared()
	{
		$testFile = $this->fixtureDir . DIRECTORY_SEPARATOR . 'test-1.rsd';
		$listener = new InterceptionListener( HTTP_STREAM_WRAPPER, $this->fixtureDir, ['http'] );

		$listener->startTestSuite( $this->suite );
		$listener->startTest( $this );
		\file_get_contents( 'http://www.google.com/' );
		$listener->endTest( $this, time() );

		$this->assertFileExists( $testFile );
		\unlink( $testFile );
	}

	/**
	 * @depends test_part_1_filename_not_cleared
	 */
	public function test_part_2_filename_not_cleared()
	{
		$this->assertEmpty(
			Http::getSaveFilename(),
			'The filename is still set to "' . Http::getSaveFilename() . '" from the previous unit test.'
		);
	}
}
?>