<?php

declare(strict_types=1);

namespace App;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Slim\Psr7\Response as SlimResponse;

/**
 * Restricts a route group to owners.
 */
final class RoleMiddleware implements MiddlewareInterface
{
    public function __construct(private Auth $auth, private string $role)
    {
    }

    public function process(Request $request, Handler $handler): Response
    {
        if ($this->role === 'owner' && !$this->auth->isOwner()) {
            $res = new SlimResponse();
            $res->getBody()->write(
                '<h1>403 Forbidden</h1><p>This page is for owners only.</p>'
            );
            return $res->withStatus(403);
        }

        return $handler->handle($request);
    }
}
