<?php

declare(strict_types=1);

namespace Omni\Controllers;

use Omni\Core\App;
use Omni\Core\View;

/** Updates: the version installed, the latest release and its notes, Install and Undo. */
final class UpdatesController
{
    public function __construct(private App $app)
    {
    }

    public function index(): void
    {
        $owner = $this->app->requireOwner();
        $updater = $this->app->updater();
        View::render('updates/index', [
            'title' => 'Updates',
            'owner' => $owner,
            'status' => $updater->status(),
            'last' => $updater->lastUpdate(),
        ]);
    }

    public function check(): void
    {
        $this->app->requireOwner();
        $status = $this->app->updater()->status(true);
        flash($status['error'] !== '' ? $status['error'] : ($status['has_update'] ? 'Omni ' . $status['manifest']['version'] . ' is available.' : 'Omni ' . $status['current'] . ' is the latest version.'), $status['error'] !== '' ? 'error' : 'success');
        redirect('/updates');
    }

    public function install(): void
    {
        $this->app->requireOwner();
        @set_time_limit(300);
        @ignore_user_abort(true);
        $result = $this->app->updater()->install();
        flash($result['message'], $result['ok'] ? ($result['state'] === 'installed' ? 'success' : 'warning') : 'error');
        redirect('/updates');
    }

    public function undo(): void
    {
        $this->app->requireOwner();
        $result = $this->app->updater()->undo();
        flash($result['message'], $result['ok'] ? 'success' : 'error');
        redirect('/updates');
    }
}
