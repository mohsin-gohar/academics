<?php
/**
 * Public Contact Intake
 */
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/tenant.php';
require_once __DIR__ . '/includes/saas.php';
startSecureSession();
require_once __DIR__ . '/config/db.php';

function contact_redirect(string $target, string $query = ''): void
{
    $url = $target === 'home' ? 'index.php' : 'contact.php';
    if ($query !== '') {
        $url .= (strpos($url, '?') === false ? '?' : '&') . $query;
    }
    $url .= $target === 'home' ? '#contact' : '#contact-form';
    header('Location: ' . $url);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: contact.php');
    exit;
}

$target = (($_POST['redirect'] ?? '') === 'home') ? 'home' : 'contact';

if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    contact_redirect($target, 'contact_error=' . urlencode('Your session expired. Please refresh and try again.'));
}

if (!empty($_POST['website'])) {
    contact_redirect($target, 'contact_error=' . urlencode('Your message could not be sent. Please try again.'));
}

if (!empty($_SESSION['contact_last']) && (time() - (int)$_SESSION['contact_last']) < 30) {
    contact_redirect($target, 'contact_error=' . urlencode('Please wait a few seconds before sending another message.'));
}
$_SESSION['contact_last'] = time();

// Session-scoped cooldown above stops double submits; this stops a client that
// simply throws away its cookie.
if (rate_limit_exceeded($pdo, 'contact', (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 5, 3600)) {
    contact_redirect($target, 'contact_error=' . urlencode('Too many messages from this network. Please try again later.'));
}

$contextSchool = saas_bootstrap_school_context($pdo);

try {
    saas_contact_store($pdo, [
        'school_id' => $contextSchool ? (int)$contextSchool['id'] : null,
        'name'      => (string)($_POST['name'] ?? ''),
        'email'     => (string)($_POST['email'] ?? ''),
        'subject'   => (string)($_POST['subject'] ?? ''),
        'message'   => (string)($_POST['message'] ?? ''),
    ]);
} catch (InvalidArgumentException $e) {
    contact_redirect($target, 'contact_error=' . urlencode($e->getMessage()));
} catch (Throwable $e) {
    error_log('Contact message failed: ' . $e->getMessage());
    contact_redirect($target, 'contact_error=' . urlencode('We could not send your message right now. Please try again.'));
}

contact_redirect($target, 'contact_success=1');
