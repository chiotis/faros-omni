<?php

declare(strict_types=1);

namespace Omni\Core;

use Omni\Models\Settings;
use ZipArchive;

/**
 * Updates Omni from its own screen. Reads release.json of the latest GitHub Release, then installs its package:
 * download, check the size and SHA-256, unpack beside the live code, swap the code folders in with renames, and ask the
 * new code whether it works. When it does not, the old code goes back (and the database, if the new code changed it).
 * Data (storage/) is never touched: no backup is needed first.
 */
final class Updater
{
    public const CHECK_EVERY = 43200;
    public const CHECK_EVERY_FAILED = 3600;
    public const MAX_PACKAGE = 20971520;
    public const MAX_FILES = 2000;
    /** What a package may hold and what is swapped: folders and single files. VERSION goes last. */
    public const DIRECTORIES = ['system', 'public/assets'];
    public const FILES = ['public/index.php', 'public/.htaccess', '.htaccess', 'CHANGELOG.md', 'README.md', 'VERSION'];
    /** What a package must hold to be an Omni. */
    private const REQUIRED = ['VERSION', 'public/index.php', 'system/Core/App.php', 'system/Core/Database.php', 'system/Core/Updater.php'];
    /** How many past updates keep their old code (for undo). */
    private const KEEP_RUNS = 2;

    /**
     * @param callable(string, int): array{status: int, body: string, error: string} $fetch GET a URL (redirects followed), with a timeout
     * @param callable(string, int, string): array{ok: bool, error: string} $download GET a URL into a file, stopping past a size
     * @param callable(): array{ok: bool, error: string, reachable: bool} $verify asks the code now in place whether it works (a request of its own)
     */
    public function __construct(
        private string $root,
        private string $storage,
        private string $dbPath,
        private Settings $settings,
        private string $releaseUrl,
        private $fetch,
        private $download,
        private $verify,
        private bool $allowLocal = false
    ) {
    }

    /** Whether the last check found a newer version, without checking again (for the header). */
    public function knownUpdate(): ?string
    {
        $kept = json_decode($this->settings->get('update_status'), true);
        $latest = is_array($kept) ? (string)($kept['manifest']['version'] ?? '') : '';
        return $latest !== '' && version_compare($latest, $this->currentVersion(), '>') ? $latest : null;
    }

    public function currentVersion(): string
    {
        return trim((string)@file_get_contents($this->root . '/VERSION'));
    }

    /**
     * What is known about the latest release, checked again when older than CHECK_EVERY (or asked).
     *
     * @return array{checked_at: string, manifest: ?array<string, mixed>, error: string, current: string, has_update: bool}
     */
    public function status(bool $force = false): array
    {
        $kept = json_decode($this->settings->get('update_status'), true);
        $kept = is_array($kept) ? $kept : null;
        $age = $kept !== null ? time() - (int)strtotime((string)($kept['checked_at'] ?? '')) : PHP_INT_MAX;
        $ttl = ($kept['error'] ?? '') === '' ? self::CHECK_EVERY : self::CHECK_EVERY_FAILED;
        if ($force || $kept === null || $age < 0 || $age >= $ttl || ($kept['url'] ?? '') !== $this->releaseUrl) {
            $answer = ($this->fetch)($this->releaseUrl, 8);
            $manifest = $answer['status'] === 200 ? $this->validateManifest(json_decode($answer['body'], true)) : null;
            $kept = [
                'checked_at' => gmdate('c'),
                'url' => $this->releaseUrl,
                'manifest' => $manifest,
                'error' => $manifest !== null ? '' : ($answer['status'] === 200 ? 'The release has no valid release.json.' : 'Could not read the latest release' . ($answer['status'] > 0 ? ' (HTTP ' . $answer['status'] . ')' : ($answer['error'] !== '' ? ': ' . $answer['error'] : '')) . '.'),
            ];
            $this->settings->set('update_status', (string)json_encode($kept, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        }
        $current = $this->currentVersion();
        $latest = (string)($kept['manifest']['version'] ?? '');
        return [
            'checked_at' => (string)$kept['checked_at'],
            'manifest' => $kept['manifest'],
            'error' => (string)$kept['error'],
            'current' => $current,
            'has_update' => $latest !== '' && version_compare($latest, $current, '>'),
        ];
    }

    /** The manifest when it is one Omni can install from, else null. @return array<string, mixed>|null */
    public function validateManifest(mixed $data): ?array
    {
        if (!is_array($data)) {
            return null;
        }
        $version = (string)($data['version'] ?? '');
        $url = (string)($data['package_url'] ?? '');
        $sha = strtolower((string)($data['sha256'] ?? ''));
        $size = $data['size'] ?? null;
        if (!preg_match('/^\d+\.\d+\.\d+$/', $version) || !preg_match('/^[0-9a-f]{64}$/', $sha) || !is_int($size) || $size < 1 || $size > self::MAX_PACKAGE || !$this->allowedUrl($url)) {
            return null;
        }
        return [
            'version' => $version,
            'min_php' => preg_match('/^\d+\.\d+$/', (string)($data['min_php'] ?? '')) ? (string)$data['min_php'] : '8.1',
            'package_url' => $url,
            'sha256' => $sha,
            'size' => $size,
            'notes' => mb_substr(is_string($data['notes'] ?? null) ? $data['notes'] : '', 0, 20000),
        ];
    }

    /**
     * Installs the latest release.
     *
     * @return array{ok: bool, state: string, message: string} state: installed, unverified (in place, but the check could not
     *         reach Omni), rolled_back, or failed (nothing changed)
     */
    public function install(): array
    {
        $status = $this->status(true);
        $manifest = $status['manifest'];
        if ($manifest === null) {
            return $this->failed($status['error'] ?: 'There is no release to install.');
        }
        if (!$status['has_update']) {
            return $this->failed('Omni ' . $status['current'] . ' is already the latest version.');
        }
        if (version_compare(PHP_VERSION, (string)$manifest['min_php'], '<')) {
            return $this->failed('Omni ' . $manifest['version'] . ' needs PHP ' . $manifest['min_php'] . ' or later; this server has ' . PHP_VERSION . '.');
        }
        if (!class_exists(ZipArchive::class)) {
            return $this->failed('This server has no ZipArchive (the zip extension), which unpacking needs.');
        }
        foreach (['', '/public', '/system', '/public/assets'] as $dir) {
            if (!is_writable($this->root . $dir)) {
                return $this->failed('Omni cannot write to ' . ($dir === '' ? 'its own folder' : ltrim($dir, '/')) . ', so it cannot replace its code. Update it with git or by uploading the files.');
            }
        }

        $updates = $this->storage . '/updates';
        if (!is_dir($updates) && !@mkdir($updates, 0775, true)) {
            return $this->failed('Cannot create ' . $updates . '.');
        }
        $lock = fopen($updates . '/install.lock', 'c');
        if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
            return $this->failed('Another update is running.');
        }
        try {
            return $this->run($manifest, $status['current'], $updates);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** @param array<string, mixed> $manifest @return array{ok: bool, state: string, message: string} */
    private function run(array $manifest, string $from, string $updates): array
    {
        $version = (string)$manifest['version'];
        // The time first, so the folders sort by when they ran; a random part, so two updates in one second never share one.
        $dir = $updates . '/' . gmdate('YmdHis') . '-' . bin2hex(random_bytes(3)) . '-' . $version;
        mkdir($dir . '/staged', 0775, true);
        $free = @disk_free_space($updates);
        if (is_float($free) && $free < 3 * (int)$manifest['size'] + 5242880) {
            $this->remove($dir);
            return $this->failed('Not enough free disk space to update.');
        }

        // Download and check.
        $package = $dir . '/package.zip';
        $got = ($this->download)((string)$manifest['package_url'], (int)$manifest['size'], $package);
        if (!$got['ok'] || !is_file($package)) {
            $this->remove($dir);
            return $this->failed('The package could not be downloaded' . ($got['error'] !== '' ? ': ' . $got['error'] : '') . '.');
        }
        if (filesize($package) !== (int)$manifest['size'] || !hash_equals((string)$manifest['sha256'], (string)hash_file('sha256', $package))) {
            $this->remove($dir);
            return $this->failed('The package downloaded is not the one the release describes (size or SHA-256). Nothing was changed.');
        }

        // Unpack beside the live code, accepting only what a package may hold.
        $error = $this->unpack($package, $dir . '/staged', $version);
        if ($error !== '') {
            $this->remove($dir);
            return $this->failed($error . ' Nothing was changed.');
        }

        // A copy of the database, used only if the new code changes it and then has to go.
        $dbVersion = $this->dbVersion($this->dbPath);
        $copy = $dir . '/omni.sqlite';
        try {
            $pdo = new \PDO('sqlite:' . $this->dbPath);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $pdo->exec('VACUUM INTO ' . $pdo->quote($copy));
            $pdo = null;
        } catch (\Throwable) {
            $this->remove($dir);
            return $this->failed('The database could not be copied before updating. Nothing was changed.');
        }

        // Everything this request still needs is loaded now, so it never mixes old and new code.
        foreach ([View::class, Router::class, Signature::class, ConnectCode::class, SecretBox::class] as $class) {
            class_exists($class);
        }

        // Swap: each live item into previous/, the staged one in. VERSION last.
        $done = [];
        foreach ($this->items() as $item) {
            $live = $this->root . '/' . $item;
            $staged = $dir . '/staged/' . $item;
            $old = $dir . '/previous/' . $item;
            if (!file_exists($staged)) {
                continue;
            }
            if (!is_dir(dirname($old))) {
                mkdir(dirname($old), 0775, true);
            }
            $hadLive = file_exists($live);
            if (($hadLive && !@rename($live, $old)) || !@rename($staged, $live)) {
                if ($hadLive && !file_exists($live) && file_exists($old)) {
                    @rename($old, $live);
                }
                $this->unswap($done, $dir);
                $this->remove($dir);
                return $this->failed('Could not move ' . $item . ' into place; the old code is back. Check the folder permissions.');
            }
            $done[] = ['item' => $item, 'had_live' => $hadLive];
        }
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }

        $report = ['from' => $from, 'to' => $version, 'at' => gmdate('c'), 'swapped' => $done, 'db_version' => $dbVersion];
        file_put_contents($dir . '/report.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        @unlink($package);

        // Ask the new code.
        $check = ($this->verify)();
        if (!$check['ok'] && ($check['reachable'] ?? true)) {
            $this->unswap($done, $dir);
            if (function_exists('opcache_reset')) {
                @opcache_reset();
            }
            if ($this->dbVersion($this->dbPath) !== $dbVersion) {
                Database::scheduleRestore($this->dbPath, $copy);
            }
            $report['result'] = 'rolled_back';
            file_put_contents($dir . '/report.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return ['ok' => false, 'state' => 'rolled_back', 'message' => 'Omni ' . $version . ' did not work here (' . $check['error'] . '), so ' . $from . ' was put back.'];
        }

        $report['result'] = $check['ok'] ? 'installed' : 'unverified';
        file_put_contents($dir . '/report.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $this->settings->set('update_status', '');
        $this->prune($updates);
        return $check['ok']
            ? ['ok' => true, 'state' => 'installed', 'message' => 'Omni ' . $version . ' is installed.']
            : ['ok' => true, 'state' => 'unverified', 'message' => 'Omni ' . $version . ' is in place, but it could not be checked (' . $check['error'] . '). Open a page to see that it works; Undo puts ' . $from . ' back.'];
    }

    /** The last update whose old code is still kept, for Undo. @return array<string, mixed>|null */
    public function lastUpdate(): ?array
    {
        foreach (array_reverse($this->runs($this->storage . '/updates')) as $dir) {
            $report = json_decode((string)@file_get_contents($dir . '/report.json'), true);
            if (is_array($report) && in_array($report['result'] ?? '', ['installed', 'unverified'], true)) {
                // Only the update that put in the version running now can be undone.
                return (string)($report['to'] ?? '') === $this->currentVersion() ? $report + ['dir' => $dir] : null;
            }
        }
        return null;
    }

    /** Puts back the code the last update replaced (not the database: it has been used since). @return array{ok: bool, state: string, message: string} */
    public function undo(): array
    {
        $last = $this->lastUpdate();
        if ($last === null || $this->currentVersion() !== (string)$last['to']) {
            return $this->failed('There is no update to undo.');
        }
        foreach ([View::class, Router::class, Signature::class, ConnectCode::class, SecretBox::class] as $class) {
            class_exists($class);
        }
        $dir = (string)$last['dir'];
        foreach ((array)$last['swapped'] as $entry) {
            $item = (string)$entry['item'];
            if (!empty($entry['had_live']) && !file_exists($dir . '/previous/' . $item)) {
                return $this->failed('The old code of ' . $item . ' is missing, so the update cannot be undone.');
            }
        }
        $this->unswap((array)$last['swapped'], $dir, true);
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
        $last['result'] = 'undone';
        unset($last['dir']);
        file_put_contents($dir . '/report.json', json_encode($last, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $this->settings->set('update_status', '');
        return ['ok' => true, 'state' => 'undone', 'message' => 'Omni ' . $last['from'] . ' is back.'];
    }

    /** Whether a path in a package is one Omni accepts. */
    public static function allowedPath(string $path): bool
    {
        if ($path === '' || str_contains($path, '\\') || str_starts_with($path, '/') || preg_match('#(^|/)\.\.?(/|$)#', $path) || str_contains($path, "\0")) {
            return false;
        }
        if (in_array($path, self::FILES, true)) {
            return true;
        }
        foreach (self::DIRECTORIES as $dir) {
            if (str_starts_with($path, $dir . '/')) {
                return true;
            }
        }
        return false;
    }

    private function unpack(string $package, string $target, string $version): string
    {
        $zip = new ZipArchive();
        if ($zip->open($package) !== true) {
            return 'The package is not a zip file.';
        }
        if ($zip->numFiles < 1 || $zip->numFiles > self::MAX_FILES) {
            $zip->close();
            return 'The package has ' . $zip->numFiles . ' files, which is not a package of Omni.';
        }
        $total = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $name = (string)($stat['name'] ?? '');
            if (str_ends_with($name, '/')) {
                continue;
            }
            $zip->getExternalAttributesIndex($i, $os, $attr);
            $isLink = $os === ZipArchive::OPSYS_UNIX && (($attr >> 16) & 0170000) === 0120000;
            if ($isLink || !self::allowedPath($name)) {
                $zip->close();
                return 'The package holds something an update may not write (' . $name . ').';
            }
            $total += (int)($stat['size'] ?? 0);
            if ($total > 5 * self::MAX_PACKAGE) {
                $zip->close();
                return 'The package unpacks to more than an update may hold.';
            }
            $dest = $target . '/' . $name;
            if (!is_dir(dirname($dest))) {
                mkdir(dirname($dest), 0775, true);
            }
            $data = $zip->getFromIndex($i);
            if (!is_string($data) || file_put_contents($dest, $data) === false) {
                $zip->close();
                return 'The package could not be unpacked (' . $name . ').';
            }
        }
        $zip->close();
        foreach (self::REQUIRED as $file) {
            if (!is_file($target . '/' . $file)) {
                return 'The package has no ' . $file . ', so it is not a whole Omni.';
            }
        }
        if (trim((string)file_get_contents($target . '/VERSION')) !== $version) {
            return 'The package is not version ' . $version . '.';
        }
        return '';
    }

    /** Reverses swaps, the last first: the new item out (to staged/, or deleted when undoing), the old one back. @param array<int, array{item: string, had_live: bool}> $done */
    private function unswap(array $done, string $dir, bool $undo = false): void
    {
        foreach (array_reverse($done) as $entry) {
            $item = (string)$entry['item'];
            $live = $this->root . '/' . $item;
            $parking = $dir . '/' . ($undo ? 'undone' : 'staged') . '/' . $item;
            if (file_exists($live)) {
                if (!is_dir(dirname($parking))) {
                    mkdir(dirname($parking), 0775, true);
                }
                @rename($live, $parking);
            }
            if (!empty($entry['had_live']) && file_exists($dir . '/previous/' . $item)) {
                @rename($dir . '/previous/' . $item, $live);
            }
        }
    }

    /** @return array<int, string> */
    private function items(): array
    {
        return array_merge(self::DIRECTORIES, self::FILES);
    }

    private function dbVersion(string $path): int
    {
        try {
            $pdo = new \PDO('sqlite:' . $path);
            return (int)$pdo->query('PRAGMA user_version')->fetchColumn();
        } catch (\Throwable) {
            return -1;
        }
    }

    /** Keeps the folders of the last KEEP_RUNS updates (their old code, for Undo). */
    private function prune(string $updates): void
    {
        $runs = $this->runs($updates);
        foreach (array_slice($runs, 0, max(0, count($runs) - self::KEEP_RUNS)) as $dir) {
            $this->remove($dir);
        }
    }

    /** @return array<int, string> the update folders, oldest first */
    private function runs(string $updates): array
    {
        $dirs = glob($updates . '/[0-9]*-*-*', GLOB_ONLYDIR) ?: [];
        sort($dirs, SORT_STRING);
        return $dirs;
    }

    private function allowedUrl(string $url): bool
    {
        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['host'])) {
            return false;
        }
        $scheme = strtolower((string)($parts['scheme'] ?? ''));
        return $scheme === 'https' || ($this->allowLocal && $scheme === 'http' && in_array($parts['host'], ['127.0.0.1', 'localhost'], true));
    }

    private function remove(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            @unlink($path);
            return;
        }
        if (!is_dir($path)) {
            return;
        }
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                $this->remove($path . '/' . $entry);
            }
        }
        @rmdir($path);
    }

    /** @return array{ok: bool, state: string, message: string} */
    private function failed(string $message): array
    {
        return ['ok' => false, 'state' => 'failed', 'message' => $message];
    }

    // ---- HTTP with PHP's streams (no cURL needed), for the App to pass in.

    /**
     * A GET with PHP's streams (no cURL needed). The status comes from the stream's own metadata, which every PHP version
     * fills (the $http_response_header variable is only set where the code names it, and is deprecated from PHP 8.5).
     *
     * @return array{status: int, body: string, error: string}
     */
    public static function fetch(string $url, int $timeout, bool $follow = true): array
    {
        $context = stream_context_create(['http' => ['timeout' => $timeout, 'ignore_errors' => true, 'follow_location' => $follow ? 1 : 0, 'max_redirects' => 5, 'header' => "User-Agent: Omni updater\r\nAccept: application/json"]]);
        $error = '';
        set_error_handler(static function (int $no, string $message) use (&$error): bool {
            $error = preg_replace('/^.*?\):\s*/', '', $message) ?? $message;
            return true;
        });
        try {
            $stream = fopen($url, 'rb', false, $context);
            $body = $stream !== false ? stream_get_contents($stream, 262144) : false;
            $lines = $stream !== false ? (array)(stream_get_meta_data($stream)['wrapper_data'] ?? []) : [];
            if ($stream !== false) {
                fclose($stream);
            }
        } finally {
            restore_error_handler();
        }
        $status = 0;
        foreach ($lines as $line) {
            // After a redirect the last status line is the one that counts.
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', (string)$line, $m)) {
                $status = (int)$m[1];
            }
        }
        return ['status' => $status, 'body' => is_string($body) ? $body : '', 'error' => $status === 0 ? ($error ?: 'no answer') : ''];
    }

    /** @return array{ok: bool, error: string} */
    public static function downloadTo(string $url, int $maxBytes, string $file): array
    {
        $context = stream_context_create(['http' => ['timeout' => 60, 'follow_location' => 1, 'max_redirects' => 5, 'header' => 'User-Agent: Omni updater']]);
        $in = @fopen($url, 'rb', false, $context);
        if ($in === false) {
            return ['ok' => false, 'error' => 'no answer from ' . (parse_url($url, PHP_URL_HOST) ?: $url)];
        }
        $out = fopen($file, 'wb');
        $written = 0;
        while (!feof($in)) {
            $chunk = fread($in, 65536);
            if ($chunk === false) {
                break;
            }
            $written += strlen($chunk);
            if ($written > $maxBytes) {
                fclose($in);
                fclose($out);
                return ['ok' => false, 'error' => 'it is larger than the release says'];
            }
            fwrite($out, $chunk);
        }
        fclose($in);
        fclose($out);
        return ['ok' => true, 'error' => ''];
    }
}
