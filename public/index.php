<?php

declare(strict_types=1);

/**
 * Front controller for Tournament Tables application.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use TournamentTables\Controllers\BaseController;
use TournamentTables\Controllers\TournamentController;
use TournamentTables\Controllers\TerrainTypeController;
use TournamentTables\Controllers\AuthController;
use TournamentTables\Controllers\RoundController;
use TournamentTables\Controllers\AllocationController;
use TournamentTables\Controllers\PublicController;
use TournamentTables\Controllers\ViewController;
use TournamentTables\Controllers\HomeController;
use TournamentTables\Controllers\MockBcpController;
use TournamentTables\Middleware\AdminAuthMiddleware;

// Error handling
error_reporting(E_ALL);
ini_set('display_errors', '0');

// Set content type for API responses
if (str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/')) {
    header('Content-Type: application/json');
}

/**
 * Output JSON safely with a fallback payload.
 */
function respondJson(array $payload): void
{
    try {
        echo json_encode($payload, JSON_THROW_ON_ERROR);
    } catch (\JsonException $e) {
        echo '{"error":"serialization_error","message":"Failed to encode JSON response"}';
    }
}

// Parse request
$method = isset($_SERVER['REQUEST_METHOD']) ? (string) $_SERVER['REQUEST_METHOD'] : 'GET';
$requestUri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '/';
$parsedPath = parse_url($requestUri, PHP_URL_PATH);
$uri = is_string($parsedPath) ? (rtrim($parsedPath, '/') ?: '/') : '/';

// Route definitions
$routes = [
    // API Routes
    'POST /api/tournaments' => ['TournamentController', 'create'],
    'GET /api/tournaments/{id}' => ['TournamentController', 'show', 'admin'],
    'DELETE /api/tournaments/{id}' => ['TournamentController', 'delete', 'admin'],
    'PUT /api/tournaments/{id}/tables' => ['TournamentController', 'updateTables', 'admin'],
    'POST /api/tournaments/{id}/tables/add' => ['TournamentController', 'addTable', 'admin'],
    'POST /api/tournaments/{id}/tables/remove' => ['TournamentController', 'removeTable', 'admin'],
    'PUT /api/tournaments/{id}/tables/count' => ['TournamentController', 'setTableCount', 'admin'],
    'GET /api/terrain-types' => ['TerrainTypeController', 'index'],
    'POST /api/auth' => ['AuthController', 'authenticate'],
    'POST /api/tournaments/{id}/rounds/{n}/import' => ['RoundController', 'import', 'admin'],
    'POST /api/tournaments/{id}/rounds/{n}/generate' => ['RoundController', 'generate', 'admin'],
    'POST /api/tournaments/{id}/rounds/{n}/publish' => ['RoundController', 'publish', 'admin'],
    'GET /api/tournaments/{id}/rounds/{n}' => ['RoundController', 'show', 'admin'],
    'PATCH /api/allocations/{id}' => ['AllocationController', 'update', 'admin'],
    'POST /api/allocations/swap' => ['AllocationController', 'swap', 'admin'],
    'GET /api/public/tournaments/{id}' => ['PublicController', 'showTournament'],
    'GET /api/public/tournaments/{id}/rounds/{n}' => ['PublicController', 'showRound'],

    // Admin View Routes (HTML) - must come before public catch-all routes
    'GET /admin' => ['HomeController', 'index'],
    'GET /admin/login' => ['ViewController', 'login'],
    'GET /admin/tournament/create' => ['ViewController', 'createTournament'],
    'GET /admin/tournament/{id}' => ['ViewController', 'showTournament', 'admin'],
    'GET /admin/tournament/{id}/round/{n}' => ['ViewController', 'showRound', 'admin'],

    // Public View Routes (HTML) - catch-all routes last
    'GET /' => ['ViewController', 'publicIndex'],
    'GET /{id}' => ['ViewController', 'publicTournament'],
    'GET /{id}/round/{n}' => ['ViewController', 'publicRound'],
];

if (getenv('APP_ENV') === 'testing' || getenv('BCP_MOCK_API_URL')) {
    // Order matters: more specific routes must come before more general ones
    $routes['GET /mock-bcp-api/{id}/pairings'] = ['MockBcpController', 'pairings'];
    $routes['GET /mock-bcp-api/{id}'] = ['MockBcpController', 'eventDetails'];
}

// Match route
$matchedRoute = null;
$params = [];

foreach ($routes as $pattern => $handler) {
    list($routeMethod, $routePath) = explode(' ', $pattern, 2);

    if ($method !== $routeMethod) {
        continue;
    }

    // Convert route pattern to regex
    $regex = preg_replace('/\{([a-z]+)\}/', '(?P<$1>[^/]+)', $routePath);
    $regex = '#^' . $regex . '$#';

    if (preg_match($regex, $uri, $matches)) {
        $matchedRoute = $handler;
        // Extract named parameters
        foreach ($matches as $key => $value) {
            if (is_string($key)) {
                $params[$key] = $value;
            }
        }
        break;
    }
}

// Handle 404
if ($matchedRoute === null) {
    http_response_code(404);
    if (str_starts_with($uri, '/api/')) {
        respondJson(['error' => 'not_found', 'message' => 'Route not found']);
    } else {
        echo '<!DOCTYPE html><html><head><title>404 Not Found</title></head><body><h1>404 Not Found</h1></body></html>';
    }
    exit;
}

// Check authentication if required
$requiresAuth = isset($matchedRoute[2]) && $matchedRoute[2] === 'admin';
if ($requiresAuth) {
    $authResult = AdminAuthMiddleware::check();
    if ($authResult !== true) {
        http_response_code(401);
        if (str_starts_with($uri, '/api/')) {
            respondJson(['error' => 'unauthorized', 'message' => $authResult]);
        } else {
            header('Location: /admin/login');
        }
        exit;
    }
}

// Controller mapping
$controllers = [
    'TournamentController' => TournamentController::class,
    'TerrainTypeController' => TerrainTypeController::class,
    'AuthController' => AuthController::class,
    'RoundController' => RoundController::class,
    'AllocationController' => AllocationController::class,
    'PublicController' => PublicController::class,
    'ViewController' => ViewController::class,
    'HomeController' => HomeController::class,
];

if (getenv('APP_ENV') === 'testing' || getenv('BCP_MOCK_API_URL')) {
    $controllers['MockBcpController'] = MockBcpController::class;
}

// Dispatch to controller
$controllerName = $matchedRoute[0];
$action = $matchedRoute[1];

if (!isset($controllers[$controllerName])) {
    http_response_code(500);
    respondJson(['error' => 'internal_error', 'message' => 'Controller not found']);
    exit;
}

try {
    $controllerClass = $controllers[$controllerName];
    $controller = new $controllerClass();

    // Get request body for POST/PUT/PATCH
    $body = null;
    if (in_array($method, ['POST', 'PUT', 'PATCH'])) {
        $contentType = isset($_SERVER['CONTENT_TYPE']) ? (string) $_SERVER['CONTENT_TYPE'] : '';

        // Handle JSON content type
        if (str_contains($contentType, 'application/json')) {
            $rawBody = file_get_contents('php://input');
            if (!empty($rawBody)) {
                try {
                    $body = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
                } catch (\JsonException $e) {
                    http_response_code(400);
                    respondJson(['error' => 'invalid_json', 'message' => 'Invalid JSON in request body']);
                    exit;
                }
            }
        }
        // Handle form-urlencoded content type (HTMX default)
        elseif (str_contains($contentType, 'application/x-www-form-urlencoded')) {
            $body = $_POST;
        }
        // Fallback: try JSON first, then form data
        else {
            $rawBody = file_get_contents('php://input');
            if (!empty($rawBody)) {
                try {
                    $body = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
                } catch (\JsonException $e) {
                    // Not valid JSON, check if we have POST data
                    $body = !empty($_POST) ? $_POST : null;
                }
            }
        }
    }

    // Call the controller action
    $result = $controller->$action($params, $body);

    // Output result (controllers handle their own response formatting)
} catch (\Throwable $e) {
    http_response_code(500);
    if (str_starts_with($uri, '/api/')) {
        respondJson([
            'error' => 'internal_error',
            'message' => 'An unexpected error occurred',
            // Only show details in development
            'debug' => getenv('APP_ENV') === 'development' ? $e->getMessage() : null,
        ]);
    } else {
        echo '<!DOCTYPE html><html><head><title>Error</title></head><body><h1>Error</h1><p>An unexpected error occurred.</p></body></html>';
    }
}
