{{-- Preview popup for documents: any link with class "doc-preview" (data-preview-url,
     data-preview-kind image|pdf) opens here at once instead of a new tab. Links to
     an image or PDF marked with data-doc-view are previewed the same way. --}}
<style>
    #doc-preview-overlay { position: fixed; inset: 0; z-index: 2100; background: rgba(16, 24, 40, .82); display: none;
        align-items: center; justify-content: center; padding: 24px; }
    #doc-preview-overlay.open { display: flex; }
    #doc-preview-box { position: relative; width: min(1100px, 100%); height: min(88vh, 100%); display: flex; flex-direction: column;
        background: #fff; border-radius: 10px; overflow: hidden; box-shadow: 0 20px 50px rgba(0, 0, 0, .35); }
    #doc-preview-bar { display: flex; align-items: center; gap: 10px; padding: 10px 14px; border-bottom: 1px solid #eaecf0; }
    #doc-preview-bar .name { font-size: 14px; font-weight: 500; color: #101828; flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    #doc-preview-bar a, #doc-preview-bar button { font-size: 13px; border: 0; background: none; color: #1d82f5; cursor: pointer; }
    #doc-preview-bar button.close-x { font-size: 22px; color: #667085; line-height: 1; }
    #doc-preview-body { flex: 1; background: #f2f4f7; display: flex; align-items: center; justify-content: center; overflow: auto; }
    #doc-preview-body img { max-width: 100%; max-height: 100%; object-fit: contain; }
    #doc-preview-body iframe { width: 100%; height: 100%; border: 0; background: #fff; }
</style>

<div id="doc-preview-overlay" role="dialog" aria-modal="true" aria-label="Document preview">
    <div id="doc-preview-box">
        <div id="doc-preview-bar">
            <span class="name" id="doc-preview-name"></span>
            <a id="doc-preview-open" href="#" target="_blank" rel="noopener">Open in new tab</a>
            <button type="button" class="close-x" id="doc-preview-close" aria-label="Close">&times;</button>
        </div>
        <div id="doc-preview-body"></div>
    </div>
</div>

<script>
    (function () {
        var overlay = document.getElementById('doc-preview-overlay');
        var body = document.getElementById('doc-preview-body');

        function kindOf(url) {
            var path = (url || '').split('?')[0].toLowerCase();
            if (/\.(png|jpe?g|gif|webp|bmp|svg)$/.test(path)) { return 'image'; }
            if (/\.pdf$/.test(path)) { return 'pdf'; }
            return null;
        }

        function open(url, kind, name) {
            body.innerHTML = '';
            if (kind === 'image') {
                var img = document.createElement('img');
                img.src = url;
                img.alt = name || '';
                body.appendChild(img);
            } else {
                var frame = document.createElement('iframe');
                frame.src = url;
                frame.title = name || 'Document';
                body.appendChild(frame);
            }
            document.getElementById('doc-preview-name').textContent = name || '';
            document.getElementById('doc-preview-open').href = url;
            overlay.classList.add('open');
        }

        function close() {
            overlay.classList.remove('open');
            body.innerHTML = '';
        }

        // Thumbnails and "View" links, in pages and in ajax-loaded tabs alike.
        document.addEventListener('click', function (e) {
            var link = e.target.closest('.doc-preview, [data-doc-view]');
            if (!link) { return; }
            var url = link.getAttribute('data-preview-url') || link.getAttribute('href');
            var kind = link.getAttribute('data-preview-kind') || kindOf(url);
            if (!url || !kind) { return; }   // not an image or PDF: leave the link alone
            e.preventDefault();
            e.stopPropagation();
            open(url, kind, link.getAttribute('data-preview-name') || link.textContent.trim());
        }, true);

        document.getElementById('doc-preview-close').addEventListener('click', close);
        overlay.addEventListener('click', function (e) { if (e.target === overlay) { close(); } });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && overlay.classList.contains('open')) { close(); } });
    })();
</script>
