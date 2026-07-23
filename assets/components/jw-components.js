/**
 * Superable Learning LMS — Backwards Compatibility Loader
 * Redirects legacy jw-components.js requests to the new sl-components.js asset.
 */
(function() {
    const currentScript = document.currentScript;
    if (currentScript && currentScript.src) {
        const slScript = document.createElement('script');
        slScript.src = currentScript.src.replace('jw-components.js', 'sl-components.js');
        slScript.defer = true;
        document.head.appendChild(slScript);
    } else {
        // Fallback if currentScript is not supported (legacy browsers)
        const slScript = document.createElement('script');
        slScript.src = 'assets/components/sl-components.js';
        slScript.defer = true;
        document.head.appendChild(slScript);
    }
})();
