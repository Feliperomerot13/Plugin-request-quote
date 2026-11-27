// The vendor still initializes the gallery and its controls. Only postpone
// registering the model-viewer element when the viewer is below the fold.
(function (loader) {
    if (!loader) return;

    function loadModule() {
        var script = document.createElement('script');
        script.type = 'module';
        script.src = loader.dataset.lumaModule;
        script.id = loader.id;
        if (loader.nonce) script.nonce = loader.nonce;
        if (loader.hasAttribute('crossorigin')) script.crossOrigin = loader.crossOrigin;
        if (loader.referrerPolicy) script.referrerPolicy = loader.referrerPolicy;
        if (loader.dataset.lumaIntegrity) script.integrity = loader.dataset.lumaIntegrity;
        loader.replaceWith(script);
    }

    function watchViewer() {
        var blocks = document.querySelectorAll('.modelViewerBlock');
        // Inline widgets may move into a product gallery after initialization.
        // Keep the original timing for those layouts and dynamically added viewers.
        if (!window.IntersectionObserver || blocks.length !== 1 ||
            blocks[0].classList.contains('elementor') || !blocks[0].closest('.elementor-shortcode')) {
            loadModule();
            return;
        }
        var observer = new IntersectionObserver(function (entries) {
            if (entries.some(function (entry) { return entry.isIntersecting; })) {
                observer.disconnect();
                loadModule();
            }
        }, { rootMargin: '380px 0px' });
        observer.observe(blocks[0].parentElement);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', watchViewer, { once: true });
    } else {
        watchViewer();
    }
})(document.currentScript);
