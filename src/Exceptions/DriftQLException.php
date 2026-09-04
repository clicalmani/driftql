<?php
namespace Tonka\DriftQL\Exceptions;

use Throwable;

/**
 * Class DriftQLException
 *
 * Custom base exception class for handling error conditions 
 * specific to the DriftQL component package.
 *
 * @package Tonka\DriftQL\Exceptions
 * @author clicalmani
 */
class DriftQLException extends \Exception
{
    /**
     * Create a new DriftQLException instance.
     *
     * @param string $message The exception error message.
     * @param int $code The exception code.
     * @param \Throwable|null $previous The previous throwable used for exception chaining.
     */
    public function __construct(string $message = "", int $code = 0, Throwable|null $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}