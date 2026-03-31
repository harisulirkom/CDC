<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class Cors
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $allowedOrigins = $this->getAllowedOrigins();
        $origin = $request->headers->get('Origin');
        $allowOrigin = $this->resolveOrigin($origin, $allowedOrigins);

        // Handle preflight OPTIONS requests
        if ($request->isMethod('OPTIONS')) {
            $response = response('', 200)
                ->header('Access-Control-Allow-Origin', $allowOrigin ?? '')
                ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept')
                ->header('Access-Control-Allow-Credentials', 'true')
                ->header('Access-Control-Max-Age', '86400'); // Cache preflight for 24 hours

            if ($allowOrigin) {
                $response->header('Vary', 'Origin');
            }

            return $response;
        }

        // Process the request
        $response = $next($request);

        // Add CORS headers to the response
        if ($allowOrigin) {
            $response->header('Access-Control-Allow-Origin', $allowOrigin);
            $response->header('Vary', 'Origin');
        }

        return $response
            ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept')
            ->header('Access-Control-Allow-Credentials', 'true');
    }

    protected function getAllowedOrigins(): array
    {
        $fromConfig = config('cors.allowed_origins', []);
        if (is_string($fromConfig)) {
            $fromConfig = array_filter(array_map('trim', explode(',', $fromConfig)));
        }

        $envList = env('CORS_ALLOWED_ORIGINS', '');
        $fromEnv = array_filter(array_map('trim', explode(',', (string) $envList)));

        $fallback = env('FRONTEND_URL', 'http://localhost:5174');

        $origins = array_merge($fromConfig ?: [], $fromEnv);
        if (empty($origins) && $fallback) {
            $origins[] = $fallback;
        }

        return array_values(array_unique($origins));
    }

    protected function resolveOrigin(?string $origin, array $allowedOrigins): ?string
    {
        if (! $origin) {
            return null;
        }

        if (in_array($origin, $allowedOrigins, true)) {
            return $origin;
        }

        return null;
    }
}
