// The embedded contact form has its own document and does not inherit WordPress CSS.
(function () {
    var stylesheet = document.getElementById('luma-brand-css');
    if (!stylesheet) return;

    function applyPalette(frame) {
        var doc = frame.contentDocument;
        if (!doc || !doc.head || doc.getElementById('luma-form-palette')) return;
        var link = doc.createElement('link');
        link.id = 'luma-form-palette';
        link.rel = 'stylesheet';
        link.href = stylesheet.href;
        doc.documentElement.classList.add('luma-contact-embed');
        doc.head.appendChild(link);
    }

    function styleForms() {
        document.querySelectorAll('iframe.hs-form-iframe').forEach(applyPalette);
    }

    document.addEventListener('load', function (event) {
        if (event.target.matches && event.target.matches('iframe.hs-form-iframe')) applyPalette(event.target);
    }, true);
    window.addEventListener('message', function (event) {
        if (event.data && event.data.type === 'hsFormCallback' && event.data.eventName === 'onFormReady') styleForms();
    });
    styleForms();
})();
