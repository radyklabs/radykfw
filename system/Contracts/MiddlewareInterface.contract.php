<?php declare (strict_types=1);

namespace System\Contracts;

interface MiddlewareInterface
{
    /** False terminates routing; implementations may send a response, never dispatch. */
    public function allow (array $route): bool;
}
