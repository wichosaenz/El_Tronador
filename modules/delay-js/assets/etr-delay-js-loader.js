/**
 * El Tronador — Delay JS Loader
 *
 * Listens for the first user interaction and then loads all delayed scripts
 * in their original DOM order to preserve dependency chains.
 */
(function () {
    var events = ['scroll', 'mousemove', 'touchstart', 'keydown', 'click'];
    var fired = false;

    function loadDelayedScripts() {
        if (fired) return;
        fired = true;

        // Remove all listeners.
        events.forEach(function (evt) {
            document.removeEventListener(evt, loadDelayedScripts, {passive: true});
            window.removeEventListener(evt, loadDelayedScripts, {passive: true});
        });

        // Collect all delayed scripts in DOM order.
        var scripts = document.querySelectorAll('script[type="etr-delay/javascript"]');
        var queue = [];

        scripts.forEach(function (el) {
            queue.push(el);
        });

        // Process the queue sequentially to respect load order.
        function processNext() {
            if (queue.length === 0) return;

            var el = queue.shift();
            var newScript = document.createElement('script');

            // Copy attributes (except type and data-etr-src).
            Array.from(el.attributes).forEach(function (attr) {
                if (attr.name === 'type' || attr.name === 'data-etr-src') return;
                newScript.setAttribute(attr.name, attr.value);
            });

            var src = el.getAttribute('data-etr-src');

            if (src) {
                // External script — load via src and wait for it.
                newScript.src = src;
                newScript.onload = processNext;
                newScript.onerror = processNext;
            } else {
                // Inline script — execute immediately.
                newScript.textContent = el.textContent;
            }

            el.parentNode.replaceChild(newScript, el);

            // For inline scripts, proceed to next immediately.
            if (!src) {
                processNext();
            }
        }

        processNext();
    }

    // Bind interaction listeners on both document and window (for scroll).
    events.forEach(function (evt) {
        document.addEventListener(evt, loadDelayedScripts, {passive: true});
        window.addEventListener(evt, loadDelayedScripts, {passive: true});
    });

    // Fallback: load scripts after 10 seconds even without interaction.
    setTimeout(loadDelayedScripts, 10000);
})();
