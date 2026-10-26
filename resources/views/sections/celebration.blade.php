{{-- Birthday / wedding anniversary / work anniversary: a greeting and a burst
     of confetti, once on the day, at the first page after signing in
     (App\Services\Employee\Celebrations). Plain JS, nothing to load. --}}
@php $celebrations = \App\Services\Employee\Celebrations::takeForToday(); @endphp

@if ($celebrations)
    <style>
        #celebrate-overlay { position: fixed; inset: 0; z-index: 2000; display: flex; align-items: center; justify-content: center;
            background: rgba(16, 24, 40, .45); animation: celebrate-fade .3s ease-out; }
        #celebrate-card { position: relative; z-index: 2; background: #fff; border-radius: 14px; max-width: 440px; width: calc(100% - 32px);
            padding: 30px 28px 24px; text-align: center; box-shadow: 0 20px 50px rgba(0, 0, 0, .25); animation: celebrate-pop .45s cubic-bezier(.2, 1.4, .4, 1); }
        #celebrate-card .c-icon { font-size: 54px; line-height: 1; margin-bottom: 10px; }
        #celebrate-card h3 { font-size: 22px; font-weight: 600; color: #101828; margin: 0 0 8px; }
        #celebrate-card p { font-size: 14px; color: #475467; margin: 0 0 6px; line-height: 1.55; }
        #celebrate-card .c-item + .c-item { border-top: 1px solid #eaecf0; margin-top: 16px; padding-top: 16px; }
        #celebrate-card button { margin-top: 18px; border: 0; border-radius: 8px; padding: 9px 26px; font-size: 14px; font-weight: 500;
            color: #fff; background: #1d82f5; cursor: pointer; }
        #celebrate-confetti { position: fixed; inset: 0; z-index: 2001; pointer-events: none; }
        @keyframes celebrate-fade { from { opacity: 0; } to { opacity: 1; } }
        @keyframes celebrate-pop { from { transform: scale(.7); opacity: 0; } to { transform: scale(1); opacity: 1; } }
    </style>

    <div id="celebrate-overlay" role="dialog" aria-modal="true" aria-label="{{ $celebrations[0]['title'] }}">
        <div id="celebrate-card">
            @foreach ($celebrations as $c)
                <div class="c-item">
                    <div class="c-icon" aria-hidden="true">{{ $c['icon'] }}</div>
                    <h3>{{ $c['title'] }}</h3>
                    <p>{{ $c['text'] }}</p>
                </div>
            @endforeach
            <button type="button" id="celebrate-close">Thank you!</button>
        </div>
    </div>
    <canvas id="celebrate-confetti"></canvas>

    <script>
        (function () {
            var canvas = document.getElementById('celebrate-confetti');
            var ctx = canvas.getContext('2d');
            var colours = ['#1d82f5', '#f04438', '#12b76a', '#f79009', '#7a5af8', '#ee46bc', '#fdb022'];
            var pieces = [];
            var running = true;

            function size() { canvas.width = window.innerWidth; canvas.height = window.innerHeight; }
            size();
            window.addEventListener('resize', size);

            // Two bursts from the lower corners, then a slow shower from the top:
            // large, bright pieces that drift down swaying, like paper.
            function piece(x, y, vx, vy) {
                return { x: x, y: y, vx: vx, vy: vy, w: 10 + Math.random() * 8, h: 14 + Math.random() * 10,
                    r: Math.random() * 6, vr: (Math.random() - .5) * .12, sway: Math.random() * 6, swaySpeed: .02 + Math.random() * .03,
                    c: colours[(Math.random() * colours.length) | 0], life: 0 };
            }
            function burst(x, angle) {
                for (var i = 0; i < 70; i++) {
                    var a = angle + (Math.random() - .5) * 1.0, speed = 7 + Math.random() * 7;
                    pieces.push(piece(x, canvas.height, Math.cos(a) * speed, -Math.sin(a) * speed));
                }
            }
            burst(0, Math.PI / 3);
            burst(canvas.width, Math.PI * 2 / 3);

            var shower = setInterval(function () {
                for (var i = 0; i < 5; i++) {
                    pieces.push(piece(Math.random() * canvas.width, -30, (Math.random() - .5) * .8, .6 + Math.random() * .8));
                }
            }, 140);
            setTimeout(function () { clearInterval(shower); }, 6000);

            function frame() {
                if (!running) { return; }
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                pieces = pieces.filter(function (p) { return p.y < canvas.height + 40 && p.life < 2400; });
                pieces.forEach(function (p) {
                    // Light gravity and air drag: a slow, swaying fall at most ~1.6px a frame.
                    p.vy = Math.min(p.vy + .045, 1.6); p.vx *= .985; p.sway += p.swaySpeed;
                    p.x += p.vx + Math.sin(p.sway) * .9; p.y += p.vy; p.r += p.vr; p.life++;
                    ctx.save(); ctx.translate(p.x, p.y); ctx.rotate(p.r); ctx.fillStyle = p.c; ctx.globalAlpha = .95;
                    ctx.fillRect(-p.w / 2, -p.h / 2, p.w, p.h * Math.abs(Math.cos(p.r * 2)) + 3);
                    ctx.restore();
                });
                if (pieces.length) { requestAnimationFrame(frame); } else { canvas.remove(); }
            }
            requestAnimationFrame(frame);

            function close() {
                var o = document.getElementById('celebrate-overlay');
                if (o) { o.remove(); }
                setTimeout(function () { running = false; if (canvas.parentNode) { canvas.remove(); } }, 4000);
            }
            document.getElementById('celebrate-close').addEventListener('click', close);
            document.getElementById('celebrate-overlay').addEventListener('click', function (e) { if (e.target === this) { close(); } });
            document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { close(); } });
        })();
    </script>
@endif
