<?php
/**
 * Minimal PSR-4 autoloader for the HanapBahay API domain classes, with NO
 * Composer / Laravel. Maps `App\` -> api/app/. Used by smoke.php and safe to
 * `require` from a `php -r` one-liner (it contains the only `$` variables, so
 * the inline snippet stays free of shell-expanded `$`).
 */
spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $appDir = __DIR__ . '/../../../app'; // api/app
    $rel    = str_replace('\\', '/', substr($class, strlen($prefix)));
    $file   = $appDir . '/' . $rel . '.php';
    if (is_file($file)) {
        require $file;
    }
});
