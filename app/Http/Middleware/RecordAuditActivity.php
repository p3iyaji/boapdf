<?php

namespace App\Http\Middleware;

use App\Services\AuditLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RecordAuditActivity
{
    public function __construct(private AuditLogger $auditLogger) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->shouldRecord($request, $response)) {
            $this->auditLogger->logFromRequest($request);
        }

        return $response;
    }

    private function shouldRecord(Request $request, Response $response): bool
    {
        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return false;
        }

        if ($request->user() === null) {
            return false;
        }

        if ($request->session()->has('errors')) {
            return false;
        }

        $status = $response->getStatusCode();

        return ($status >= 200 && $status < 300) || ($status >= 300 && $status < 400);
    }
}
