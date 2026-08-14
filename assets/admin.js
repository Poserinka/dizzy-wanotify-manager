(() => {
    'use strict';
    const config = window.dizzyWAnotify;
    const button = document.querySelector('[data-test-connection]');
    const result = document.querySelector('[data-test-result]');
    if (!config || !button || !result) return;

    button.addEventListener('click', async () => {
        button.disabled = true;
        result.className = 'dizzy-wa-test-result';
        result.textContent = config.testing;
        try {
            const body = new URLSearchParams({action: 'dizzy_wanotify_test_connection', nonce: config.nonce});
            const response = await fetch(config.ajaxUrl, {method: 'POST', credentials: 'same-origin', body});
            const json = await response.json();
            if (!json.success) throw new Error(json.data?.message || config.failed);
            result.classList.add('is-success');
            result.textContent = json.data.message;
        } catch (error) {
            result.classList.add('is-error');
            result.textContent = error.message || config.failed;
        } finally {
            button.disabled = false;
        }
    });
})();
