<?php

declare(strict_types=1);

namespace App;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Slim\Psr7\Response as SlimResponse;

/**
 * Redirects unauthenticated requests to the login page.
 */
final class AuthMiddleware implements MiddlewareInterface
{
    /** @param array<int,string> $publicPaths full paths that bypass auth */
    public function __construct(
        private Auth $auth,
        private string $basePath,
        private array $publicPaths = []
    ) {
    }

    public function process(Request $request, Handler $handler): Response
    {
        $path = $request->getUri()->getPath();

        if ($this->auth->check() || in_array($path, $this->publicPaths, true)) {
            return $handler->handle($request);
        }

        return (new SlimResponse())
            ->withHeader('Location', $this->basePath . '/login')
            ->withStatus(302);
    }
}
