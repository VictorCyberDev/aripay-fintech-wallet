<?php
/**
 * Centralized error-handling bootstrap.
 *
 * Loaded at the very top of every page (before db.php) so that internal
 * errors are never leaked to clients while still being recorded to the
 * server log instead of being silently swallowed.
 */

// Never render internal errors / stack traces in responses...
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');

// ...but make sure every error is reported and written to the log.
error_reporting(E_ALL);
ini_set('log_errors', '1');

// If the host has no error_log configured, keep one alongside the app.
if (in_array(ini_get('error_log'), ['', false, null], true)) {
    $log_dir = __DIR__ . '/logs';
    if (!is_dir($log_dir)) {
        @mkdir($log_dir, 0775, true);
    }
    ini_set('error_log', $log_dir . '/php-error.log');
}

/**
 * Record an application error with contextual detail to the server log.
 * Full details go to the log only; users see a generic message.
 */
function log_app_error(string $context, Throwable $e): void
{
    error_log(sprintf(
        '[Ari-Pay] %s | %s: %s in %s:%d',
        $context,
        get_class($e),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine()
    ));
}

/**
 * Build a generic, safe notification card for user-facing error output.
 * Internal exception details must never be passed in here.
 */
function user_error_notice(string $message = 'A system error occurred. Please try again shortly.'): string
{
    return "<div class='notification-card border-rose-500/30 bg-rose-500/10 text-rose-400'>"
         . "<i data-lucide='shield-alert' class='w-4 h-4 shrink-0'></i>"
         . "<span>" . htmlspecialchars($message) . "</span></div>";
}

// Last-resort safety net: anything that escapes a try/catch (e.g. a failed
// DB connection in db.php or an unguarded query) is logged and turned into a
// friendly message instead of a blank/half-rendered page.
set_exception_handler(function (Throwable $e): void {
    log_app_error('Uncaught exception', $e);
    if (!headers_sent()) {
        http_response_code(500);
    }
    echo user_error_notice();
});
