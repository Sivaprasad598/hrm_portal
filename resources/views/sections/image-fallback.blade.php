{{--
    Broken images: any <img> that fails to load (a deleted avatar, a moved logo, a dead link) swaps to a
    placeholder - a person for profile pictures, a neutral picture for everything else. Loaded in <head> so it
    also catches images added later by tables, tabs and pop-ups.
    An <img data-no-fallback> keeps its broken image.
--}}
<script>
    (function () {
        var personPlaceholder = @json(asset('img/gravatar.png'));
        var picturePlaceholder = 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(
            '<svg xmlns="http://www.w3.org/2000/svg" width="120" height="90" viewBox="0 0 120 90">'
            + '<rect width="120" height="90" rx="6" fill="#eef2f7"/>'
            + '<path d="M28 66l20-24 14 16 10-12 20 20z" fill="#cbd5e1"/>'
            + '<circle cx="82" cy="30" r="8" fill="#cbd5e1"/></svg>'
        );

        // Profile pictures: stored under /avatar/, or shown in an avatar spot
        function isPerson(img) {
            var src = img.getAttribute('src') || '';
            return /\/avatar\/|gravatar|profile/i.test(src)
                || /rounded-circle|taskEmployeeImg|avatar|profile/i.test(img.className || '')
                || !!(img.closest && img.closest('.media, .avatar, .profileImg, .taskEmployeeImg'));
        }

        function useFallback(img) {
            if (!img || img.tagName !== 'IMG' || img.hasAttribute('data-no-fallback')) return;
            // Only once per image, so a missing placeholder cannot loop
            if (img.getAttribute('data-fallback-applied')) return;
            img.setAttribute('data-fallback-applied', '1');
            img.removeAttribute('srcset');
            img.src = isPerson(img) ? personPlaceholder : picturePlaceholder;
            if (!img.getAttribute('alt')) img.setAttribute('alt', '');
        }

        // Load errors do not bubble, so listen in the capture phase on the whole document
        document.addEventListener('error', function (e) { useFallback(e.target); }, true);

        // Images that failed before this ran
        document.addEventListener('DOMContentLoaded', function () {
            Array.prototype.forEach.call(document.images, function (img) {
                if (img.complete && img.getAttribute('src') && img.naturalWidth === 0) useFallback(img);
            });
        });
    })();
</script>
