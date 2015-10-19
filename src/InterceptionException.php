<?php namespace Kshabazz\Interception;

/**
 * Class InterceptionException
 *
 * @package \Kshabazz\Interception
 */
class InterceptionException extends \Exception
{
	const
		GENERIC = 1,
		BAD_SAVE_DIR = 2,
		BAD_STREAM_WRAPPER = 3,
		BAD_SAVE_CONST = 4,
		NO_FILENAME = 5;

	/**
	 * @var array Error messages.
	 */
	private $messages = [
		self::GENERIC => 'An errors has occurred.',
		self::BAD_SAVE_DIR => 'Second argument must be a directory where to save RSD files.',
		self::BAD_STREAM_WRAPPER => 'You must set the stream wrapper class as the first argument.',
		self::BAD_SAVE_CONST => 'Constant "%s" did not map to a valid directory where to save RSD files.',
		self::NO_FILENAME => 'Please set a filename to save the contents of the request.'
	];

	/**
	 * Construct
	 *
	 * @param string $pCode
	 * @param array $pData
	 * @param string $customMessage Override built-in messages with your own, will also be used for custom error codes.
	 */
	public function __construct( $pCode, array $pData = NULL, $customMessage = NULL )
	{
		// When custom message is anything other than a string or null.
		if ( !(\is_string($customMessage) && \strlen($customMessage) > 0) && !\is_null($customMessage) )
		{
			throw new \InvalidArgumentException(
				'Third parameter must be a string of length greater than zero or null.'
			);
		}
		$message = $this->getMessageByCode( $pCode, $pData, $customMessage );
		parent::__construct( $message, $pCode );
	}

	/**
	 * Returns a textual error message for an error code
	 *
	 * @param integer $code error code or another error object for code reuse
	 * @param array $data additional data to insert into message, processed by vsprintf()
	 * @param string $customMessage Override the built-in message with your own.
	 * @return string error message
	 */
	public function getMessageByCode( $code, array $data = NULL, $customMessage = NULL )
	{
		// Use custom message when set.
		if ( !empty($customMessage) && \strlen($customMessage) > 0 )
		{
			return \vsprintf($customMessage, $data );
		}

		// When no entry for code found, return a generic error message.
		if ( !\array_key_exists($code, $this->messages) )
		{
			return $this->messages[ self::GENERIC ];
		}

		// Parse variables in the error message when present.
		if ( \is_array($data) )
		{
			return \vsprintf( $this->messages[$code], $data );
		}

		return $this->messages[ $code ];
	}
}
?>