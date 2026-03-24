<?php

declare(strict_types=1);

namespace WCDO\Http;

class Router
{
    private array $routes = [];

    public function add(string $method, string $pattern, callable $handler): void
    {
        $this->routes[] = [
            'method'  => strtoupper($method),
            'pattern' => '#^' . $pattern . '$#',
            'handler' => $handler,
        ];
    }

    public function get(string $pattern, callable $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, callable $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    public function put(string $pattern, callable $handler): void
    {
        $this->add('PUT', $pattern, $handler);
    }

    public function delete(string $pattern, callable $handler): void
    {
        $this->add('DELETE', $pattern, $handler);
    }

    public function dispatch(string $method, string $uri): void
    {
        $method = strtoupper($method);
        // Supprime les query strings (?foo=bar) de l'URI
        $uri = strtok($uri, '?');

        $methodMatched = false;

        foreach ($this->routes as $route) {
            if (!preg_match($route['pattern'], $uri, $matches)) {
                continue;
            }

            $methodMatched = true;

            if ($route['method'] !== $method) {
                continue;
            }

            // Récupère uniquement les paramètres nommés (ex: id, numero)
            $params = array_filter(
                $matches,
                fn($key) => !is_int($key),
                ARRAY_FILTER_USE_KEY
            );

            ($route['handler'])($params);
            return;
        }

        if ($methodMatched) {
            // L'URI existe mais pas avec cette méthode HTTP
            Response::error('Méthode non autorisée', 405);
        }

        Response::notFound('Route non trouvée');
    }
}
