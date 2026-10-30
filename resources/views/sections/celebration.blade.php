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

            // The theme's own colours first, then bright ones
            var root = getComputedStyle(document.documentElement);
            var colours = [root.getPropertyValue('--epic-blue').trim() || '#1d82f5', root.getPropertyValue('--epic-green').trim() || '#12b76a',
                '#f04438', '#12b76a', '#f79009', '#7a5af8', '#ee46bc', '#fdb022', '#2e90fa'];
            var pieces = [];
            var running = true;

            function size() { canvas.width = window.innerWidth; canvas.height = window.innerHeight; }
            size();
            window.addEventListener('resize', size);

            // Party poppers: bursts from the lower corners and the middle shoot up,
            // then everything falls in slow motion and lands on the bottom of the
            // screen; two seconds on, the cleaning robot drives along the floor and
            // sweeps it all away.
            function piece(x, y, vx, vy) {
                var round = Math.random() < .25;
                return { x: x, y: y, vx: vx, vy: vy, w: round ? 8 : 9 + Math.random() * 7, h: round ? 8 : 14 + Math.random() * 10,
                    round: round, r: Math.random() * 6, vr: (Math.random() - .5) * .3, sway: Math.random() * 6,
                    swaySpeed: .05 + Math.random() * .06, c: colours[(Math.random() * colours.length) | 0],
                    rest: 2 + Math.random() * 14, landedAt: 0, blown: false };
            }
            function pop(x, angle, spread, count) {
                for (var i = 0; i < count; i++) {
                    var a = angle + (Math.random() - .5) * spread, speed = 16 + Math.random() * 16;
                    pieces.push(piece(x, canvas.height + 10, Math.cos(a) * speed, -Math.sin(a) * speed));
                }
            }
            function volley() {
                pop(0, Math.PI / 3.2, .9, 90);
                pop(canvas.width, Math.PI - Math.PI / 3.2, .9, 90);
                pop(canvas.width / 2, Math.PI / 2, .7, 70);
            }
            volley();
            setTimeout(volley, 1100);

            var started = null, last = null;
            var POP = 450, EASE = 500, SLOW = .3, REST = 2000, FADE = 600;
            var blower = null;   // the robot along the floor: {x, started}
            var robot = new Image();
            robot.src = "{{ asset('img/epic/cleaner-robot.png') }}";
            var ROBOT_H = Math.min(80, Math.round(window.innerHeight * .1)), ROBOT_W = Math.round(ROBOT_H * 268 / 300);

            // How fast time runs: the pop at full speed, then a quick ease into
            // slow motion for the whole fall; full speed again for the wind
            function timeScale(t) {
                if (t < POP || blower) { return 1; }
                var k = Math.min(1, (t - POP) / EASE);
                return 1 - k * (1 - SLOW);
            }

            function frame(now) {
                if (!running) { return; }
                if (started === null) { started = last = now; }
                var t = now - started, real = Math.min(2.5, (now - last) / 16.67), dt = real * timeScale(t);
                var floor = canvas.height - 4;
                last = now;

                ctx.clearRect(0, 0, canvas.width, canvas.height);

                // Everything down and rested two seconds: in comes the robot
                var flying = pieces.some(function (p) { return !p.landedAt; });
                var latest = pieces.reduce(function (m, p) { return Math.max(m, p.landedAt || 0); }, 0);
                if (!blower && t > 1500 && !flying && pieces.length && now - latest > REST) {
                    blower = { x: -ROBOT_W, started: now };
                }

                if (blower) {
                    blower.x += 7 * real;
                    pieces.forEach(function (p) {
                        // swept up by its front brush
                        if (p.landedAt && !p.sweptAt && p.x < blower.x + ROBOT_W - 10) {
                            if (!p.kicked && Math.random() < .35) {
                                // blown ahead by the brush: a little hop forward, lands again
                                p.kicked = true;
                                p.landedAt = 0;
                                p.vx = 5 + Math.random() * 9;
                                p.vy = -(3 + Math.random() * 6);
                                p.vr = (Math.random() - .5) * .5;
                            } else {
                                p.sweptAt = now;
                            }
                        }
                    });
                }

                pieces = pieces.filter(function (p) {
                    if (p.sweptAt && now - p.sweptAt > 280) { return false; }
                    return p.x > -80 && p.x < canvas.width + 80 && p.y > -400
                        && (!p.landedAt || now - p.landedAt < REST + 6000 + FADE);
                });

                pieces.forEach(function (p) {
                    var alpha = .95;

                    if (p.sweptAt) {
                        // picked up: drawn into the brush, shrinking away
                        var k = Math.min(1, (now - p.sweptAt) / 280);
                        var mouthX = blower.x + ROBOT_W * .62, mouthY = canvas.height - 8;
                        p.x += (mouthX - p.x) * .18 * real;
                        p.y += (mouthY - p.y) * .18 * real;
                        alpha = .95 * (1 - k);
                        p.w *= .93; p.h *= .93;
                    } else if (p.landedAt) {
                        // On the floor: still, lying flat (a fade only if the wind never came)
                        alpha = .95 * Math.max(0, 1 - Math.max(0, now - p.landedAt - REST - 6000) / FADE);
                    } else {
                        p.vy += (p.blown ? .25 : .42) * dt;
                        p.vx *= Math.pow(.985, dt);
                        p.vy = Math.min(p.vy, 10);
                        p.sway += p.swaySpeed * dt;
                        p.x += (p.vx + Math.sin(p.sway) * 1.2) * dt;
                        p.y += p.vy * dt;
                        p.r += p.vr * dt;

                        // Coming down onto the floor: it stays there
                        if (!p.blown && p.vy > 0 && p.y >= floor - p.rest) {
                            p.y = floor - p.rest;
                            p.r = (Math.random() - .5) * .6 + (Math.random() < .5 ? 0 : Math.PI / 2);
                            p.landedAt = now;
                        }
                    }

                    ctx.save(); ctx.translate(p.x, p.y); ctx.rotate(p.r); ctx.fillStyle = p.c; ctx.globalAlpha = alpha;
                    if (p.round) {
                        ctx.beginPath(); ctx.arc(0, 0, p.w / 2, 0, Math.PI * 2); ctx.fill();
                    } else {
                        var h = p.landedAt ? p.h : p.h * Math.abs(Math.cos(p.r * 2)) + 3;
                        ctx.fillRect(-p.w / 2, -h / 2, p.w, h);
                    }
                    ctx.restore();
                });

                // The robot itself, trundling along the floor with a slight rock
                var windOn = blower && blower.x < canvas.width + 40;
                if (windOn && robot.complete && robot.naturalWidth) {
                    var rock = Math.sin((now - blower.started) / 110) * .025;
                    ctx.save();
                    ctx.globalAlpha = 1;
                    ctx.translate(blower.x + ROBOT_W / 2, canvas.height);
                    ctx.rotate(rock);
                    ctx.drawImage(robot, -ROBOT_W / 2, -ROBOT_H, ROBOT_W, ROBOT_H);
                    ctx.restore();
                }

                if (pieces.length || t < 1500 || windOn) { requestAnimationFrame(frame); } else { canvas.remove(); }
            }
            requestAnimationFrame(frame);

            function close() {
                var o = document.getElementById('celebrate-overlay');
                if (o) { o.remove(); }
            }
            document.getElementById('celebrate-close').addEventListener('click', close);
            document.getElementById('celebrate-overlay').addEventListener('click', function (e) { if (e.target === this) { close(); } });
            document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { close(); } });
        })();
    </script>
@endif
