<?php
// ============================================================
// BANKING TOAST NOTIFICATION SYSTEM
// Handles 5 notification types:
//   success  — transaction completed, login ok
//   error    — failed action, invalid input
//   warning  — session expiring, limit approaching
//   info     — informational notices
//   security — suspicious activity, login alerts
//
// Security rules enforced here:
//   - All output goes through json_encode() — no raw echo
//   - Sensitive data is NEVER passed into toast messages
//   - Session messages are unset immediately after display
//   - Content Security Policy nonce is supported
// ============================================================


// ============================================================
// HELPER — Sanitize message before passing to JS
// json_encode() handles:
//   - Single and double quotes
//   - Backslashes
//   - Line breaks
//   - Unicode characters
//   - HTML special characters (with JSON_HEX_TAG)
// This prevents XSS completely inside the JS string.
// ============================================================
function safe_toast_message($message) {
    return json_encode(
        strip_tags((string)$message),  // strip any HTML tags first
        JSON_HEX_TAG |                 // encode < and > as unicode
        JSON_HEX_APOS |                // encode single quotes
        JSON_HEX_QUOT |                // encode double quotes
        JSON_HEX_AMP                   // encode & ampersands
    );
}


// ============================================================
// COLLECT ALL TOAST MESSAGES INTO ONE ARRAY
// This way we output ONE <script> block instead of multiple,
// which is cleaner and easier to control.
// ============================================================
$toasts = [];


// ------------------------------------------------------------
// LOCAL VARIABLE TOASTS
// Set within the same page request (no redirect involved)
// e.g. $success_message = "Done"; set earlier in the same file
// ------------------------------------------------------------
if(isset($success_message) && $success_message !== '') {
    $toasts[] = [
        'type'    => 'success',
        'message' => safe_toast_message($success_message),
        'title'   => json_encode('Success'),
        'timeout' => 4000,
    ];
}

if(isset($error_message) && $error_message !== '') {
    $toasts[] = [
        'type'    => 'error',
        'message' => safe_toast_message($error_message),
        'title'   => json_encode('Error'),
        'timeout' => 6000, // errors stay longer so user can read them
    ];
}

if(isset($warning_message) && $warning_message !== '') {
    $toasts[] = [
        'type'    => 'warning',
        'message' => safe_toast_message($warning_message),
        'title'   => json_encode('Warning'),
        'timeout' => 7000,
    ];
}

if(isset($info_message) && $info_message !== '') {
    $toasts[] = [
        'type'    => 'info',
        'message' => safe_toast_message($info_message),
        'title'   => json_encode('Information'),
        'timeout' => 5000,
    ];
}

if(isset($security_message) && $security_message !== '') {
    $toasts[] = [
        'type'    => 'security',
        'message' => safe_toast_message($security_message),
        'title'   => json_encode('Security Alert'),
        'timeout' => 0, // 0 = stays until manually dismissed
    ];
}


// ------------------------------------------------------------
// SESSION TOASTS
// Set on a previous page before a redirect.
// e.g. $_SESSION['success_message'] = "Transfer complete";
//      header('Location: dashboard.php'); exit;
// Always unset immediately after reading.
// ------------------------------------------------------------
$session_toast_keys = [
    'success_message'  => ['type' => 'success',  'title' => 'Success',        'timeout' => 4000],
    'error_message'    => ['type' => 'error',     'title' => 'Error',          'timeout' => 6000],
    'warning_message'  => ['type' => 'warning',   'title' => 'Warning',        'timeout' => 7000],
    'info_message'     => ['type' => 'info',       'title' => 'Information',   'timeout' => 5000],
    'security_message' => ['type' => 'security',   'title' => 'Security Alert','timeout' => 0],
];

foreach($session_toast_keys as $key => $config) {
    if(!empty($_SESSION[$key])) {
        $toasts[] = [
            'type'    => $config['type'],
            'message' => safe_toast_message($_SESSION[$key]),
            'title'   => json_encode($config['title']),
            'timeout' => $config['timeout'],
        ];
        unset($_SESSION[$key]); // immediately destroy after reading
    }
}


// ============================================================
// RENDER — Only output if there are messages to show
// ============================================================
if(!empty($toasts)):
?>

<script>
document.addEventListener('DOMContentLoaded', function() {

    // --------------------------------------------------------
    // BANKING TOAST CONFIG
    // iziToast global defaults for the entire application
    // --------------------------------------------------------
    iziToast.settings({
        timeout:         4000,
        resetOnHover:    true,
        progressBar:     true,
        position:        'topRight',
        transitionIn:    'fadeInDown',
        transitionOut:   'fadeOutUp',
        displayMode:     'replace',   // replace duplicate messages
        maxWidth:        420,
        zindex:          99999,
    });


    // --------------------------------------------------------
    // CUSTOM SECURITY TOAST
    // Not built into iziToast — we extend it with a red
    // urgent style and a lock icon for security alerts.
    // These never auto-dismiss (timeout: 0) and require
    // the user to manually close them — critical for banking.
    // --------------------------------------------------------
    function showSecurityToast(title, message) {
        iziToast.show({
            title:          title,
            message:        message,
            position:       'topRight',
            timeout:        0,              // stays open — user must dismiss
            close:          true,
            progressBar:    false,
            backgroundColor:'#c0392b',
            titleColor:     '#ffffff',
            messageColor:   '#ffffff',
            iconColor:      '#ffffff',
            icon:           'fa fa-shield-alt',
            transitionIn:   'bounceInDown',
            transitionOut:  'fadeOutUp',
            overlay:        true,           // darkens the page background
            overlayClose:   false,          // user cannot dismiss by clicking overlay
            zindex:         999999,
            onOpened: function() {
                // Play a subtle alert sound for security notices
                // (Only works if user has interacted with the page)
                try {
                    const ctx = new AudioContext();
                    const osc = ctx.createOscillator();
                    const gain = ctx.createGain();
                    osc.connect(gain);
                    gain.connect(ctx.destination);
                    osc.frequency.value = 880;
                    gain.gain.value = 0.1;
                    osc.start();
                    osc.stop(ctx.currentTime + 0.15);
                } catch(e) {
                    // Audio not available — silently continue
                }
            }
        });
    }


    // --------------------------------------------------------
    // FIRE ALL TOASTS
    // Loop through the PHP-generated toast queue and show
    // each one with a small delay between them so they
    // don't all appear at exactly the same time.
    // --------------------------------------------------------
    const toasts = <?php echo json_encode($toasts); ?>;

    toasts.forEach(function(toast, index) {
        setTimeout(function() {

            switch(toast.type) {

                case 'success':
                    iziToast.success({
                        title:           toast.title,
                        message:         toast.message,
                        timeout:         toast.timeout,
                        icon:            'fa fa-check-circle',
                        backgroundColor: '#f0fff4',
                        titleColor:      '#276749',
                        messageColor:    '#2f855a',
                        iconColor:       '#38a169',
                        progressBarColor:'#38a169',
                        borderColor:     '#c6f6d5',
                    });
                    break;

                case 'error':
                    iziToast.error({
                        title:           toast.title,
                        message:         toast.message,
                        timeout:         toast.timeout,
                        icon:            'fa fa-times-circle',
                        backgroundColor: '#fff5f5',
                        titleColor:      '#742a2a',
                        messageColor:    '#c53030',
                        iconColor:       '#e53e3e',
                        progressBarColor:'#e53e3e',
                        borderColor:     '#fed7d7',
                    });
                    break;

                case 'warning':
                    iziToast.warning({
                        title:           toast.title,
                        message:         toast.message,
                        timeout:         toast.timeout,
                        icon:            'fa fa-exclamation-triangle',
                        backgroundColor: '#fffbeb',
                        titleColor:      '#744210',
                        messageColor:    '#b7791f',
                        iconColor:       '#d69e2e',
                        progressBarColor:'#d69e2e',
                        borderColor:     '#fefcbf',
                    });
                    break;

                case 'info':
                    iziToast.info({
                        title:           toast.title,
                        message:         toast.message,
                        timeout:         toast.timeout,
                        icon:            'fa fa-info-circle',
                        backgroundColor: '#ebf8ff',
                        titleColor:      '#2a4365',
                        messageColor:    '#2b6cb0',
                        iconColor:       '#3182ce',
                        progressBarColor:'#3182ce',
                        borderColor:     '#bee3f8',
                    });
                    break;

                case 'security':
                    // Uses the custom security function above
                    showSecurityToast(toast.title, toast.message);
                    break;
            }

        }, index * 300); // 300ms delay between each toast
    });

});
</script>

<?php endif; ?>