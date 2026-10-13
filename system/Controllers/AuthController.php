<?php

declare(strict_types=1);

namespace Omni\Controllers;

use DateTimeZone;
use Omni\Core\App;
use Omni\Core\View;

/** Setup (the owner's account, while there is none), sign-in and sign-out. */
final class AuthController
{
    public const MIN_PASSWORD = 12;
    private const LOGIN_MAX = 8;
    private const LOGIN_WINDOW = 900;

    public function __construct(private App $app)
    {
    }

    public function setupForm(array $values = [], string $error = ''): void
    {
        if ($this->app->owners->exists()) {
            redirect('/login');
        }
        View::render('setup/form', [
            'title' => 'Set up Omni',
            'values' => $values + ['name' => '', 'email' => '', 'timezone' => 'Europe/Athens'],
            'error' => $error,
            'timezones' => DateTimeZone::listIdentifiers(),
        ], 'bare');
    }

    public function setup(): void
    {
        if ($this->app->owners->exists()) {
            redirect('/login');
        }
        $values = [
            'name' => trim((string)($_POST['name'] ?? '')),
            'email' => strtolower(trim((string)($_POST['email'] ?? ''))),
            'timezone' => (string)($_POST['timezone'] ?? 'UTC'),
        ];
        $password = (string)($_POST['password'] ?? '');
        $error = match (true) {
            $values['name'] === '' => 'Enter your name.',
            filter_var($values['email'], FILTER_VALIDATE_EMAIL) === false => 'Enter a valid email address.',
            mb_strlen($password) < self::MIN_PASSWORD => 'The password needs at least ' . self::MIN_PASSWORD . ' characters.',
            $password !== (string)($_POST['password_confirm'] ?? '') => 'The two passwords are not the same.',
            !in_array($values['timezone'], DateTimeZone::listIdentifiers(), true) => 'Choose a time zone from the list.',
            default => '',
        };
        if ($error !== '') {
            http_response_code(422);
            $this->setupForm($values, $error);
            return;
        }

        $id = $this->app->owners->createFirst($values['name'], $values['email'], $password);
        if ($id === 0) {
            redirect('/login');
        }
        $this->app->secrets->ensureKey();
        $this->app->settings->set('timezone', $values['timezone']);
        $this->app->signIn($id);
        flash('Omni is ready. Add your first site.');
        redirect('/');
    }

    public function loginForm(string $email = '', string $error = ''): void
    {
        if (!$this->app->owners->exists()) {
            redirect('/setup');
        }
        if (isset($_SESSION['owner_id']) && $this->app->owners->find((int)$_SESSION['owner_id']) !== null) {
            redirect('/');
        }
        View::render('auth/login', ['title' => 'Sign in', 'email' => $email, 'error' => $error], 'bare');
    }

    public function login(): void
    {
        if (!$this->app->owners->exists()) {
            redirect('/setup');
        }
        $ip = $this->app->clientIp();
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $wait = $this->app->attempts->retryAfter('login', $ip, self::LOGIN_MAX, self::LOGIN_WINDOW);
        if ($wait > 0) {
            http_response_code(429);
            $this->loginForm($email, 'Too many attempts. Try again in ' . max(1, (int)ceil($wait / 60)) . ' min.');
            return;
        }

        $owner = $this->app->owners->findByEmail($email);
        $password = (string)($_POST['password'] ?? '');
        if ($owner === null || !password_verify($password, (string)$owner['password_hash'])) {
            $this->app->attempts->record('login', $ip);
            http_response_code(401);
            $this->loginForm($email, 'The email or the password is not right.');
            return;
        }
        $this->app->attempts->clear('login', $ip);
        $this->app->owners->rehashIfNeeded((int)$owner['id'], (string)$owner['password_hash'], $password);
        $this->app->signIn((int)$owner['id']);
        redirect('/');
    }

    public function logout(): void
    {
        $_SESSION = [];
        session_destroy();
        redirect('/login');
    }
}
