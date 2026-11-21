<?php

namespace Crater\Http\Controllers\V1\Admin;

use Crater\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ApiDocsController extends Controller
{
    /**
     * Serve the tracked OpenAPI 3.1 specification from a fixed path.
     */
    public function __invoke(Request $request): Response
    {
        $path = resource_path('openapi/openapi.yaml');

        if (! is_file($path)) {
            abort(404);
        }

        return response((string) file_get_contents($path), 200, [
            'Content-Type' => 'application/yaml; charset=utf-8',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
