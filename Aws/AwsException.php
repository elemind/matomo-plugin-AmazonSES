<?php

/**
 * AmazonSES plugin for Matomo
 *
 * @link    https://github.com/elemind/matomo-plugin-AmazonSES
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\AmazonSES\Aws;

class AwsException extends \RuntimeException
{
    /** @var string */
    private $awsErrorCode;

    /** @var int */
    private $httpStatus;

    public function __construct(string $message, string $awsErrorCode = '', int $httpStatus = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->awsErrorCode = $awsErrorCode;
        $this->httpStatus = $httpStatus;
    }

    public function getAwsErrorCode(): string
    {
        return $this->awsErrorCode;
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }
}
