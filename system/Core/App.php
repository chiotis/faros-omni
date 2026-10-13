<?php

declare(strict_types=1);

namespace Omni\Core;

use Omni\Controllers\ApiController;
use Omni\Controllers\AuthController;
use Omni\Controllers\SitesController;
use Omni\Models\Attempts;
use Omni\Models\Owners;
use Omni\Models\Settings;
use Omni\Models\Sites;
use PDO;

/** Wires the parts together and answers one request: the API for sites under /api/v1, the owner's screens elsewhere. */
final class App
{
    public PDO $db;
    public Owners $owners;
    public Sites $sites;
    public Settings $settings;
    public Attempts $attempts;
    public SecretBox $secrets;

    /** @param array<string, mixed> $config */
    public function __construct(public array $config)
    {
        $this->db = Database::open((string)$config['db_path']);
        $this->owners = new Owners($this->db);
        $this->sites = new Sites($this->db);
        $this->settings = new Settings($this->db);
        $this->attempts = new Attempts($this->db);
        $this->secrets = new SecretBox((string)$config['key_path']);
    }

    public function run(): void
    {
        $method = (string)($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $path = '/' . trim((string)parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH), '/');

        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: same-origin');

        if (str_starts_with($path, '/api/')) {
            $api = new ApiController($this);
            $router = new Router();
            $router->get('/api/v1/ping', fn() => $api->ping());
            $router->post('/api/v1/connect', fn() => $api->connect());
            $router->post('/api/v1/report', fn() => $api->report($path));
            $router->post('/api/v1/disconnect', fn() => $api->disconnect($path));
            if (!$router->dispatch($method, $path)) {
                ApiController::error(404, 'not_found', 'There is nothing at this address.');
            }
            return;
        }

        header('X-Frame-Options: DENY');
        header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; frame-ancestors 'none'; form-action 'self'; base-uri 'none'");
        $this->startSession();
        date_default_timezone_set($this->settings->get('timezone', 'UTC') ?: 'UTC');

        if ($method === 'POST' && !csrf_valid()) {
            http_response_code(403);
            View::render('errors/message', ['title' => 'Expired form', 'message' => 'The form was open too long or came from elsewhere. Go back, reload the page and try again.'], 'bare');
            return;
        }

        $auth = new AuthController($this);
        $sites = new SitesController($this);
        $router = new Router();
        $router->get('/setup', fn() => $auth->setupForm());
        $router->post('/setup', fn() => $auth->setup());
        $router->get('/login', fn() => $auth->loginForm());
        $router->post('/login', fn() => $auth->login());
        $router->post('/logout', fn() => $auth->logout());
        $router->get('/', fn() => $sites->index());
        $router->post('/sites', fn() => $sites->create());
        $router->get('/sites/{id}', fn(string $id) => $sites->show((int)$id));
        $router->post('/sites/{id}/code', fn(string $id) => $sites->newCode((int)$id));
        $router->post('/sites/{id}/rename', fn(string $id) => $sites->rename((int)$id));
        $router->post('/sites/{id}/remove', fn(string $id) => $sites->remove((int)$id));
        if (!$router->dispatch($method, $path)) {
            http_response_code(404);
            View::render('errors/message', ['title' => 'Not found', 'message' => 'There is nothing at this address.'], 'bare');
        }
    }

    /** The signed-in owner, or a redirect to setup (no owner yet) or to sign-in. @return array<string, mixed> */
    public function requireOwner(): array
    {
        if (!$this->owners->exists()) {
            redirect('/setup');
        }
        $owner = isset($_SESSION['owner_id']) ? $this->owners->find((int)$_SESSION['owner_id']) : null;
        if ($owner === null) {
            redirect('/login');
        }
        return $owner;
    }

    public function signIn(int $ownerId): void
    {
        session_regenerate_id(true);
        $_SESSION['owner_id'] = $ownerId;
        $this->owners->touchLogin($ownerId);
    }

    public function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    }

    /** This Omni's own address, as a site enters it. */
    public function baseUrl(): string
    {
        return ($this->isHttps() ? 'https' : 'http') . '://' . (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
    }

    public function clientIp(): string
    {
        return (string)($_SERVER['REMOTE_ADDR'] ?? '');
    }

    private function startSession(): void
    {
        ini_set('session.use_strict_mode', '1');
        session_name('omni_session');
        session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $this->isHttps(), 'httponly' => true, 'samesite' => 'Lax']);
        session_start();
    }
}
