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
if (strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') === 0) {
    header('Content-Type: application/json');
}

// Parse request
$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = rtrim($uri, '/') ?: '/';

// Route definitions
$routes = [
    // API Routes
    'POST /api/tournaments' => ['TournamentController', 'create'],
    'GET /api/tournaments/{id}' => ['TournamentController', 'show', 'admin'],
    'DELETE /api/tournaments/{id}' => ['TournamentController', 'delete', 'admin'],
    'PUT /api/tournaments/{id}/tables' => ['TournamentController', 'updateTables', 'admin'],
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

    // View Routes (HTML)
    'GET /' => ['HomeController', 'index'],
    'GET /tournament/create' => ['ViewController', 'createTournament'],
    'GET /tournament/{id}' => ['ViewController', 'showTournament', 'admin'],
    'GET /tournament/{id}/round/{n}' => ['ViewController', 'showRound', 'admin'],
    'GET /public' => ['ViewController', 'publicIndex'],
    'GET /public/{id}' => ['ViewController', 'publicTournament'],
    'GET /public/{id}/round/{n}' => ['ViewController', 'publicRound'],
    'GET /login' => ['ViewController', 'login'],
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
    if (strpos($uri, '/api/') === 0) {
        echo json_encode(['error' => 'not_found', 'message' => 'Route not found']);
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
        if (strpos($uri, '/api/') === 0) {
            echo json_encode(['error' => 'unauthorized', 'message' => $authResult]);
        } else {
            header('Location: /login');
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
    echo json_encode(['error' => 'internal_error', 'message' => 'Controller not found']);
    exit;
}

try {
    $controllerClass = $controllers[$controllerName];
    $controller = new $controllerClass();

    // Get request body for POST/PUT/PATCH
    $body = null;
    if (in_array($method, ['POST', 'PUT', 'PATCH'])) {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        // Handle JSON content type
        if (strpos($contentType, 'application/json') !== false) {
            $rawBody = file_get_contents('php://input');
            if (!empty($rawBody)) {
                $body = json_decode($rawBody, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    http_response_code(400);
                    echo json_encode(['error' => 'invalid_json', 'message' => 'Invalid JSON in request body']);
                    exit;
                }
            }
        }
        // Handle form-urlencoded content type (HTMX default)
        elseif (strpos($contentType, 'application/x-www-form-urlencoded') !== false) {
            $body = $_POST;
        }
        // Fallback: try JSON first, then form data
        else {
            $rawBody = file_get_contents('php://input');
            if (!empty($rawBody)) {
                $body = json_decode($rawBody, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
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
    if (strpos($uri, '/api/') === 0) {
        echo json_encode([
            'error' => 'internal_error',
            'message' => 'An unexpected error occurred',
            // Only show details in development
            'debug' => getenv('APP_ENV') === 'development' ? $e->getMessage() : null,
        ]);
    } else {
        echo '<!DOCTYPE html><html><head><title>Error</title></head><body><h1>Error</h1><p>An unexpected error occurred.</p></body></html>';
    }
}
