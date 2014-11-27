<?php namespace Kshabazz\Tests\Interception;

use \Kshabazz\Interception\StreamWrappers\Http;

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
}
?>