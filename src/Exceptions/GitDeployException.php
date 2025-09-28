<?php

declare(strict_types=1);

namespace BenitoQuib\GitDeploy\Exceptions;

use Exception;

/**
 * Base exception for GitDeploy package
 */
class GitDeployException extends Exception
{
    public function __construct(string $message = "", int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}