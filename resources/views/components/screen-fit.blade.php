{{--
    Every page is laid out at one design width and scaled to the screen, so laptops, monitors and TVs all show the same
    layout, just larger or smaller. Viewport heights use --app-vh, the screen height in those scaled pixels.
    Phones and tablets are not scaled: they get the desktop-only notice instead (see <x-desktop-only>).
--}}
<script>
    // The saved theme is applied before the page paints, so a dark page never flashes white.
    try { document.documentElement.classList.toggle('dark', localStorage.getItem('agapay-theme') === 'dark'); } catch (e) {}

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
