(function () {
    'use strict';
    const HEARTBEAT_INTERVAL_MS = 10 * 60 * 1000;

    function updateCsrfTokens(token) {
        if (!token) return;
        document.querySelectorAll('input[name="csrf_token"]').forEach(function (input) {
            input.value = token;
        });
    }

    async function fetchCsrfToken() {
        try {
            const baseUrl = (typeof SITE_URL !== 'undefined' && SITE_URL) ? SITE_URL : '';
            const response = await fetch(baseUrl + '/api/csrf-token.php', { cache: 'no-store' });
            if (response.ok) {
                const data = await response.json();
                if (data && data.csrf_token) {
                    updateCsrfTokens(data.csrf_token);
                }
            }
        } catch (err) { }
    }

    setInterval(fetchCsrfToken, HEARTBEAT_INTERVAL_MS);

    window.addEventListener('focus', function () {
        fetchCsrfToken();
    });

    if (typeof jQuery !== 'undefined') {
        jQuery(document).ajaxError(function (event, jqXHR) {
            if (jqXHR.status === 403 && jqXHR.responseJSON && jqXHR.responseJSON.error === 'CSRF_EXPIRED') {
                if (jqXHR.responseJSON.csrf_token) {
                    updateCsrfTokens(jqXHR.responseJSON.csrf_token);
                }

                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Session Refreshed',
                        text: 'Your security session was updated. Please re-submit your form.',
                        confirmButtonText: 'OK'
                    });
                }
            }
        });
    }
})();