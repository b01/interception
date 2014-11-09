The purpose of this library is to record the request (1) of URI resources and play them back from local cache. When an
interception stream wrapper is registered, for a given protocol, it will handle all URI request for that protocol.
Acting as a middleman, the first request will be allowed so that it can be saved locally; this save is then played back
for all subsequent calls. Please note that playback is indefinite, until the local cache is deleted, or the interception
stream wrapper is unregistered.

** Disclaimer: This library will only work with PHP streams. Other PHP extensions such as cURL, are not supported. **

1 request - consist of the headers and the payload are saved as *.rsd
2 rsd - stand for "raw socket data" file. No encoding is done.


## Requirements

* PHP 5.6
* PHP Streams


## How it works

The built-in wrapper for protocols like HTTP are first unregistered, then replaced with the StreamWrappers\Http wrapper.
When an HTTP request is made using stream PHP functions \fopen or \file_get_contents, the interception wrapper will then
make a TCP connection and return that as a resource for those functions.

Only content read using the returned TCP resource will be saved to a file when \fclose is called. If at a later time,
another request is made to that same URL, a file resource to the saved request is initialized and returned for those
functions. What ever content that was retrieved from the previous TCP connection will be served up for any following
request; to that exact URL.

In cases where this is the first call to a URL, using \fopen, but \foef did not return TRUE; then only partial content
will be saved. At minimum the HEADER for the request will be saved.

If two or more request to the same URL are made using \fopen or \file_get_contents, and before any are closed; They will
all have independent TCP resources. They will each save a file on \fclose, with the later overwriting the previous save.


## Examples

### How to save HTTP request for playback during unit test.

```php
use \Kshabazz\Interception\StreamWrappers\Http;

class HttpTest extends \PHPUnit_Framework_TestCase
{
    static public function setUpBeforeClass()
    {
        // Unregister the built-in PHP HTTP protocol stream wrapper.
        \stream_wrapper_unregister( 'http' );

        // Pick a place where we want to save request for playback.
        Http::setSaveDir( './fixtures/' );

        // Register the Interception stream wrapper for the HTTP protocol.
        \stream_register_wrapper(
            'http',
            '\\Kshabazz\\Interception\\StreamWrappers\\Http',
            \STREAM_IS_URL
        );
    }

    /**
     * Make sure we restore the original HTTP stream wrapper for the test environment.
     */
    static public function tearDownAfterClass()
    {
        \stream_wrapper_restore( 'http' );
    }

    /**
     * Example test case.
     */
    public function test_setSaveFile()
    {
        // You can also specify the filename for the local cache.
        Http::setSaveFilename( 'test-example' );

        // Will generate a file:  ./fixtures/test-example.rsd
        \file_get_contents( 'http://www.example.com' );

        $file = FIXTURES_PATH . DIRECTORY_SEPARATOR . $fileName . '.rsd';
        $this->assertTrue( \file_exists($file) );
        \unlink( $file );
    }
}
```

### How to use the Interception test listener with PHPUnit

You can Simplify your life by using the InterceptionListener with the @interception annotation. This works as a
replacement for the code above. Instead using the manual way; you can write a unit test as follows:

```xml
<!-- in your phpunit.xml add the listener like so: -->
<listeners>
    <listener class="\Kshabazz\Interception\InterceptionListener">
        <arguments>
            <string>Http</string>
            <string>./fixtures</string>
        </arguments>
    </listener>
</listeners>
```

```php
// Then in you unit test:
/**
 * Setup and tear down will happen in the InterceptionListener class.
 *
 * @interception ignore-annotation-test
 */
public function test_interception_annotation()
{
    $handle = \fopen( 'http://www.example.com/', 'r' );
    \fclose( $handle );
    $filename = FIXTURES_PATH . DIRECTORY_SEPARATOR . 'ignore-annotation-test.rsd';
    $this->assertTrue( \file_exists($filename) );
}
```

It will automatically register/unregister the Interception Http stream wrapper class for the test suite.

## Run Unit Test

```bash
phpunit.phar -c test/phpunit.xml
```