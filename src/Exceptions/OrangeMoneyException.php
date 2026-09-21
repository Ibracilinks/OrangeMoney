<?php

namespace Ibracilinks\OrangeMoney\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Thrown when Orange Money cannot be reached, rejects a request,
 * or answers with something that is not a valid JSON object.
 */
class OrangeMoneyException extends RuntimeException
{
    /**
     * @param int|null   $statusCode   HTTP status, or null when no response was received
     * @param array|null $responseData Decoded JSON body of the response, when there is one
     */
    public function __construct(
        string $message,
        private readonly ?int $statusCode = null,
        private readonly ?array $responseData = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function getStatusCode(): ?int
    {
        return $this->statusCode;
    }

    public function getResponseData(): ?array
    {
        return $this->responseData;
    }
}
