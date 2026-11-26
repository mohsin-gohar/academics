<?php
/**
 * Backwards-compatible entry point for the authenticated multi-role page smoke suite.
 *
 * Run only from CLI after configuring an isolated test database and starting
 * the application server. The canonical role/tenant checks live in
 * storage/smoke_pages.php so this legacy filename cannot drift into a weaker
 * duplicate test implementation.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

if (!in_array(getenv('APP_ENV') ?: '', ['test', 'testing'], true)) {
    fwrite(STDERR, "Refusing to run page smoke tests outside APP_ENV=test/testing.\n");
    exit(1);
}

require __DIR__ . '/../storage/smoke_pages.php';
