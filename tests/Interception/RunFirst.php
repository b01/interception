<?php namespace Kshabazz\Tests\Interception;

use \Kshabazz\Interception\InterceptionListener,
	\Kshabazz\Interception\StreamWrappers\Http;

class RunFirst extends \PHPUnit_Framework_TestCase
{
	/**
	 * @expectedException \Kshabazz\Interception\InterceptionException
	 * @expectedExceptionMessage Please set a directory to save the request files
	 * @group RunFirst
	 */
	public function test_set_non_existing_directory()
	{
		var_dump( Http::getSaveDir() );
	}

	/**
	 * @group RunFirst
	 */
	public function test_setting_save_dir_with_fixture_path_as_string()
	{
		$interceptionListener = new InterceptionListener( 'Http', 'FIXTURES_PATH' );
		$interceptionListener->startTestSuite( new \PHPUnit_Framework_TestSuite() );
		$this->assertEquals( FIXTURES_PATH, Http::getSaveDir() );
	}
}
?>