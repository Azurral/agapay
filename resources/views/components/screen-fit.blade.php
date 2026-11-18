{{--
    Every page is laid out at one design width and scaled to the screen, so laptops, monitors and TVs all show the same
    layout, just larger or smaller. Viewport heights use --app-vh, the screen height in those scaled pixels.
    Phones and tablets are not scaled: they get the desktop-only notice instead (see <x-desktop-only>).
--}}
<script>
    // The theme is applied before the page paints, so a dark page never flashes white. Signed-in pages carry the
    // account's choice (data-palette, data-dark) and this browser keeps a copy for the pages before sign-in.
    (function () {
        const root = document.documentElement;
        try {
            if (root.dataset.dark !== undefined) {
                localStorage.setItem('agapay-theme', root.dataset.dark === '1' ? 'dark' : 'light');
            }
            root.classList.toggle('dark', root.dataset.dark !== undefined ? root.dataset.dark === '1' : localStorage.getItem('agapay-theme') === 'dark');
            if (root.dataset.palette) {
                localStorage.setItem('agapay-palette', root.dataset.palette);
            } else {
                root.dataset.palette = localStorage.getItem('agapay-palette') || @js(config('agapay.default_theme'));
            }
        } catch (e) {}
    })();

    (function () {
        const DESIGN_WIDTH = 1600;
        const touchOnly = window.matchMedia('(hover: none) and (pointer: coarse)');
        function fit() {
            const zoom = touchOnly.matches ? 1 : window.innerWidth / DESIGN_WIDTH;
            const root = document.documentElement;
            root.style.zoom = zoom;
            root.style.setProperty('--app-vh', (window.innerHeight / zoom) + 'px');
        }
        fit();
        window.addEventListener('resize', fit);
    })();
</script>
