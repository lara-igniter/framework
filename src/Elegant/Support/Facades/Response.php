<?php

namespace Elegant\Support\Facades;

/**
 * @method static \Elegant\Http\JsonResponse json($data = [], int $status = 200, array $headers = [], int $options = 0)
 * @method static \CI_Output getResponse()
 *
 * @see \Elegant\Http\Response
 */
class Response extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'response';
    }
}
