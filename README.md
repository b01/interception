# Table of Contents

* Introduction
* Requirements
* How it works
* Examples
  * How to save HTTP request for playback during unit test (Manual way)
  * Setup interception unit tests annotations with the InterceptionListener in PHPUnit

## Introduction

The purpose of this library is to record the request (1) of URI resources and play them back from local cache. When an
interception stream wrapper is registered, for a given protocol, it will handle all URI request for that protocol.
Acting as a middleman, the first request will be allowed so that it can be saved locally; this save is then played back
for all subsequent calls. Please note that playback is indefinite, until the local cache is deleted, or the interception
stream wrapper is unregistered.

The intended use of this library was to aid in mocking/simulating HTTP request during Unit tests runs. By allowing a
real request to a service to be saved and played back during unit test. Since this works at the PHP stream level,
existing code should only need minimal change, if any.

Beside fopen and file_get_contents, there is also a Guzzle handler which can be used in order for this to work with
code that uses Guzzle. See example using (Guzzle ~5.0)[#how-can-i-use-this-with-guzzle]

## Disclaimer

This library will only work with PHP streams. Other PHP extensions such as cURL, are not supported.

1. request - consist of the headers and the payload are saved as *.rsd
2. rsd - stand for "raw socket data" file. No encoding is done.


## Requirements

* PHP ~5.4
* PHPUnit ~4.7


## How it works

The built-in wrapper for the protocol HTTP/HTTPS are first unregistered, then replaced with the Interception
StreamWrappers\Http wrapper. Once registered, you must specify a filename using Http::setSaveFilename(), which will
be used to save the response to a file with that name. When an HTTP/HTTPS request is made using PHP stream functions
fopen() or file_get_contents(), the interception wrapper will then make a TCP connection and return that as a
resource for those functions. Once eof() has been called on the resource, a file is saved using the name provided;
Which will contain the header and any content retrieved.

A note about fopen(). Unlike file_get_contents(), all content is not read at one time. Only content read using (fread
 on) the returned TCP resource will be saved to a file once fclose() is called.

Once a filename has been set, all HTTP request will get the response saved in that file. So it is important to
remember that if you want a different request, you need to provide a new file name. Or you can restore the default
Http functionality by calling stream_wrapper_restore( 'http' ), which will remove the Interception stream wrapper.

Now that you have the response from the request saved, you can use Interceptions Http stream wrapper class
(StreamWrappers\Http) to simulated the request. There is nothing else you need to do as long as the
StreamWrappers\Http class is registered as PHP stream wrapper handler. Since this is at the stream wrapper layer, it
will work for any code that uses PHP Streams.

In cases where this is the first call to a URL, using \fopen, but \foef did not return TRUE; then only partial content
will be saved. At minimum the HEADER for the request will be saved.

If two or more request to the same URL are made using \fopen or \file_get_contents, and before any are closed; They will
all have independent TCP resources. They will each save a file on \fclose, with the later overwriting the previous save.


## Examples

### How to save HTTP request for playback during unit test (Manual way)

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

### How to use the Interception test listener with PHPUnit (in a streamlined way)

Interception comes with a PHPUnit test listener which allows you to use annotations with your unit test to simplify
saving request. This works as a replacement for the manual way, and automates saving and serving up HTTP request
during runs. There requires some additional setup however.

1. Set a constant that points to the path where you want the request to be save. This can be done in your unit test
   bootstrap file, assuming you have one, like so:
```php
<?php
...
// Define a FIXTURES_PATH constant in your bootstrap file, set to whatever path you like.
$fixturesPath = \realpath( __DIR__ . DIRECTORY_SEPARATOR . 'fixtures' );
\define( 'FIXTURES_PATH', $fixturesPath );
...
?>
```
2. In your PHP Unit configuration file, add the listener like so:
```xml
<?xml version="1.0" encoding="utf-8" ?>
<phpunit bootstrap="tests/bootstrap.php"
         strict="true"
         checkForUnintentionallyCoveredCode="true">
     ...
    <listeners>
        <listener class="\Kshabazz\Interception\InterceptionListener">
            <arguments>
                <!-- The first parameter must be the Interception class that will handle to protocol. -->
                <string>\Kshabazz\Interception\StreamWrappers\Http</string>
                <!-- The second parameter can be a path or a constant that is set to a path. -->
                <string>FIXTURES_PATH</string>
                <!-- The third parameter should list the protocols to handle. -->
                <array>
                    <element>
                        <string>http</string>
                    </element>
                    <element>
                        <string>https</string>
                    </element>
                </array>
            </arguments>
        </listener>
    </listeners>
</phpunit>
```

3. Now you can write unit test and use the "@interception" annotation.
```php
/**
 * Now the HTTP request will be stored in the file "example-dot-com.rsd"
 *
 * @interception example-dot-com
 */
public function test_interception_annotation()
{
    $responseBody = file_get_content( 'http://www.example.com/', 'r' );
    $filename = FIXTURES_PATH . DIRECTORY_SEPARATOR . 'example-dot-com.rsd';
    $this->assertFileExists( \file_exists($filename) );
}
```

### How can I intercept multiple HTTP(S) request

When use use the InterceptionListener you can intercept multiple HTTP request in a single unit test by using the
"@interceptionPersist <filename>" annotation. Like so:

```php
/**
* Now the HTTP request will be stored in the file "example-dot-com.rsd"
*
* @interceptionPersist example-dot-com
*/
public function test_interception_annotation()
{
    $responseBody = file_get_content( 'http://www.example.com/', 'r' );
    $responseBody = file_get_content( 'http://www.example.com/', 'r' );

    $filename1 = FIXTURES_PATH . DIRECTORY_SEPARATOR . 'example-dot-com-1.rsd';
    $filename2 = FIXTURES_PATH . DIRECTORY_SEPARATOR . 'example-dot-com-2.rsd';

    $this->assertFileExists( \file_exists($filename1) );
    $this->assertFileExists( \file_exists($filename2) );
}
```

Notice each time you make a request, the filename will be appended with the number of the request.

### How can I use this with Guzzle

```php
use \GuzzleHttp\Client,
    \Kshabazz\Interception\StreamWrappers\Http,
    \Kshabazz\Interception\GuzzleHandler;

// Set the file to save the response to, in your unit test, you could set this with the "@interception" annotation also.
Http::setSaveFilename( 'google-dot-come' );

// Interception Guzzle compatible stream handler
$streamHandler = new GuzzleHandler();

// Have Guzzle use the Interception stream handler, so request can be intercepted.
$httpClient = new Client([
	'handler' => $streamHandler
]);

// Make the request.
$httpClient->get( 'http://www.google.com/ );
```


## How do I update responses.

1. Delete the *.rsd file in your fixtures directory, so the next time you
   run tests, a new one will be saved.


## Run Unit Test

```bash
./vendor/bin/phpunit
```