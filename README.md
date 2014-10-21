The purpose of this library is to record the request (1) of URI resources and play them back from local cache. When an
interception stream wrapper is registered, for a given protocol, it will handle all URI request for that protocol.
Acting as a middleman, the first request will be allowed so that it can be saved locally; this save is then played back
for all subsequent calls. Please note that playback is indefinite, until the local cache is deleted, or the interception
stream wrapper is unregistered.

** Disclaimer: This library will only work with PHP streams. Other PHP extensions such as cURL, are not supported. **

1 request - consist of the headers and the payload are saved as *.rsd
2 rsd - stand for "raw socket data" file. No encoding is done.


## Examples

### How to save HTTP request for playback during unit test.

```php
use \Kshabazz\Interception\StreamWrappers\Http;

class HttpTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
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

    public function test_http_interception_of_file_get_contents()
    {
        $content = \file_get_contents( 'http://www.example.com' );
        $this->assertContains( 'HTTP/1.0 200 OK', $content );
    }
}
```


## Run Unit Test

```bash
phpunit.phar -c test/phpunit.xml
```