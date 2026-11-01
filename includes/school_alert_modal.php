<?php
/**
 * School Emergency Alert — premium animated modal overlay.
 *
 * Expects: $activeAlert (array|null). Optional: $alertManageUrl (string|null)
 * for a "Manage notice" link shown to staff.
 *
 * Renders nothing when there is no active alert.
 */
if (empty($activeAlert) || !is_array($activeAlert)) {
    return;
}

$alertId        = (int)$activeAlert['id'];
$alertType      = (string)($activeAlert['alert_type'] ?? 'Emergency');
$alertTitle     = (string)($activeAlert['title'] ?? '');
$alertMessage   = (string)($activeAlert['message'] ?? '');
$alertEffective = (string)($activeAlert['effective_date'] ?? '');
$alertEnd       = (string)($activeAlert['end_date'] ?? '');
$manageUrl      = isset($alertManageUrl) && $alertManageUrl ? (string)$alertManageUrl : null;
?>

<!-- School Emergency Alert Modal -->
<div class="school-alert-overlay" id="schoolAlertOverlay-<?php echo $alertId; ?>"
     data-alert-id="<?php echo $alertId; ?>"
     role="dialog" aria-modal="true" aria-labelledby="schoolAlertTitle-<?php echo $alertId; ?>"
     aria-describedby="schoolAlertDesc-<?php echo $alertId; ?>">
    <div class="school-alert-dialog">
        <button type="button" class="school-alert-dismiss" aria-label="Close emergency notice">
            <i class="fas fa-times" aria-hidden="true"></i>
        </button>

        <div class="school-alert-rings" aria-hidden="true">
            <span class="school-alert-ring school-alert-ring-1"></span>
            <span class="school-alert-ring school-alert-ring-2"></span>
            <span class="school-alert-ring school-alert-ring-3"></span>
            <span class="school-alert-ring school-alert-ring-4"></span>
        </div>

        <div class="school-alert-content">
            <div class="school-alert-kicker">
                <span class="school-alert-kicker-dot" aria-hidden="true"></span>
                <?php echo e(strtoupper($alertType)); ?><span class="school-alert-kicker-suffix"> &middot; Official Notice</span>
            </div>

            <h2 class="school-alert-title" id="schoolAlertTitle-<?php echo $alertId; ?>">
                <?php echo e($alertTitle); ?>
            </h2>

            <p class="school-alert-message" id="schoolAlertDesc-<?php echo $alertId; ?>">
                <?php echo e($alertMessage); ?>
            </p>

            <div class="school-alert-meta">
                <i class="fas fa-calendar-days" aria-hidden="true"></i>
                Effective: <?php echo e(formatDate($alertEffective)); ?>
                <?php if ($alertEnd !== ''): ?>
                    &nbsp;&middot;&nbsp; Reopening: <?php echo e(formatDate($alertEnd)); ?>
                <?php endif; ?>
            </div>

            <button type="button" class="school-alert-ack">
                <span class="school-alert-ack-icon"><i class="fas fa-check" aria-hidden="true"></i></span>
                <span class="school-alert-ack-label">Acknowledge &amp; Continue</span>
            </button>

            <?php if ($manageUrl): ?>
                <div class="school-alert-manage">
                    <a href="<?php echo e($manageUrl); ?>"><i class="fas fa-pen-to-square" aria-hidden="true"></i> Manage notice</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';
    var overlay = document.getElementById('schoolAlertOverlay-<?php echo $alertId; ?>');
    if (!overlay) { return; }

    // The modal is often rendered inside a glass panel that uses
    // backdrop-filter/transform. Those create a containing block, which would
    // pin position:fixed to the panel instead of the viewport. Re-parent the
    // overlay to <body> so it always covers the full screen.
    if (overlay.parentNode !== document.body) {
        document.body.appendChild(overlay);
    }

    var alertId = '<?php echo $alertId; ?>';
    var storageKey = 'academicsAlertAck-' + alertId;

    // Already acknowledged in a previous visit -> keep quiet.
    try {
        if (localStorage.getItem(storageKey) === '1' ||
            sessionStorage.getItem('academicsAlertDismiss-' + alertId) === '1') {
            overlay.classList.add('is-suppressed');
            overlay.style.display = 'none';
            return;
        }
    } catch (e) { /* storage unavailable */ }

    var dialog   = overlay.querySelector('.school-alert-dialog');
    var dismiss  = overlay.querySelector('.school-alert-dismiss');
    var ack      = overlay.querySelector('.school-alert-ack');
    var wasHidden = document.body.style.overflow;

    function open() {
        document.body.classList.add('school-alert-open');
        document.body.style.overflow = 'hidden';
        overlay.classList.add('is-open');
        // Focus the acknowledgement button for keyboard users.
        window.setTimeout(function () { try { ack.focus(); } catch (e) { } }, 260);
    }

    function close(mode) {
        overlay.classList.remove('is-open');
        overlay.classList.add('is-closing');
        document.body.classList.remove('school-alert-open');
        document.body.style.overflow = wasHidden;
        window.setTimeout(function () {
            overlay.style.display = 'none';
            overlay.classList.add('is-suppressed');
        }, 320);
        try {
            if (mode === 'ack') { localStorage.setItem(storageKey, '1'); }
            else { sessionStorage.setItem('academicsAlertDismiss-' + alertId, '1'); }
        } catch (e) { /* ignore */ }
    }

    // Open with a short delay so the page paints first.
    window.setTimeout(open, 180);

    if (dismiss) { dismiss.addEventListener('click', function () { close('dismiss'); }); }
    if (ack)     { ack.addEventListener('click', function () { close('ack'); }); }

    overlay.addEventListener('click', function (e) {
        // Click on the backdrop (not inside the dialog) dismisses.
        if (e.target === overlay) { close('dismiss'); }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && overlay.classList.contains('is-open')) {
            close('dismiss');
        }
    });

    // Basic focus containment: keep Tab inside the dialog.
    dialog.addEventListener('keydown', function (e) {
        if (e.key !== 'Tab') { return; }
        var focusable = dialog.querySelectorAll('button, a[href], input, [tabindex]:not([tabindex="-1"])');
        if (!focusable.length) { return; }
        var first = focusable[0];
        var last  = focusable[focusable.length - 1];
        if (e.shiftKey && document.activeElement === first) {
            e.preventDefault(); last.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
            e.preventDefault(); first.focus();
        }
    });
})();
</script>
<?php
// Keep variables out of the global scope after rendering.
unset($alertId, $alertType, $alertTitle, $alertMessage, $alertEffective, $alertEnd, $manageUrl);
?>
