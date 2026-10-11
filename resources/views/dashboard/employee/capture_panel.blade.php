{{--
    The photograph and the place, as the attendance settings ask for them.

    Shared by the clock-in modal and the clock-out modal. One pair of settings
    (attendance_settings.clock_in_photo / .clock_in_location) governs both ends
    of the day, so both ends ask for the same things in the same way.

    The element ids are fixed rather than prefixed because only one of the two
    modals can be open at a time; sharing them is what lets one copy of the
    camera script serve both.

    Expects: $captureRules  ['photo' => off|optional|mandatory, 'location' => ...]
--}}

@if ($captureRules['photo'] !== 'off' || $captureRules['location'] !== 'off')
    <div class="row mt-2" id="clock-capture">
        @if ($captureRules['photo'] !== 'off')
            <div class="col-md-6 mb-2">
                <label class="f-12 text-dark-grey d-block">
                    Photo
                    @if ($captureRules['photo'] === 'mandatory')
                        <span class="text-red">*</span>
                    @else
                        <span class="text-lightest f-11">(optional)</span>
                    @endif
                </label>
                <div class="border rounded position-relative"
                     style="overflow:hidden; background:#f1f5f9; aspect-ratio:4/3">
                    <video id="capture-video" autoplay playsinline muted
                           style="width:100%; height:100%; object-fit:cover; display:none"></video>
                    <img id="capture-preview" alt=""
                         style="width:100%; height:100%; object-fit:cover; display:none">
                    <div id="capture-message"
                         class="d-flex align-items-center justify-content-center text-center p-2 f-12 text-lightest"
                         style="width:100%; height:100%">
                        Starting the camera…
                    </div>
                </div>
                <canvas id="capture-canvas" style="display:none"></canvas>
                <div class="mt-1">
                    <button type="button" class="btn btn-sm btn-outline-secondary f-12"
                            id="capture-take" style="display:none">Take photo</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary f-12"
                            id="capture-again" style="display:none">Take it again</button>
                </div>
            </div>
        @endif

        @if ($captureRules['location'] !== 'off')
            <div class="col-md-6 mb-2">
                <label class="f-12 text-dark-grey d-block">
                    @lang('app.location')
                    @if ($captureRules['location'] === 'mandatory')
                        <span class="text-red">*</span>
                    @else
                        <span class="text-lightest f-11">(optional)</span>
                    @endif
                </label>
                <div class="border rounded p-2 f-12" style="background:#f8fafc; min-height:72px">
                    <div id="place-status" class="text-lightest">Finding where you are…</div>
                    <div id="place-coords" class="mt-1" style="display:none">
                        <i class="fa fa-map-marker-alt text-red mr-1"></i>
                        <span id="place-text"></span>
                        <a href="#" id="place-map" target="_blank" class="d-block f-11 mt-1">See it on a map</a>
                    </div>
                </div>
            </div>
        @endif
    </div>

    <input type="hidden" id="capture-photo-data">
    <input type="hidden" id="capture-latitude">
    <input type="hidden" id="capture-longitude">

    <script>
        (function () {
            const rules = @json($captureRules);

            // ajaxModal loads a modal fragment through both .load() and
            // Bootstrap's remote option, so this can run twice for one opening.
            // Release anything a previous run left holding before starting
            // again, or the camera light stays on with nobody watching it.
            if (typeof window.clockInStopCamera === 'function') {
                window.clockInStopCamera();
            }

            let stream = null;

            function stopCamera() {
                if (stream) {
                    stream.getTracks().forEach(function (track) { track.stop(); });
                    stream = null;
                }
            }

            // The modal closing must release the camera. This partial is
            // fetched again every time a modal opens, so the handler is bound
            // once and reads the live stream off the window rather than closing
            // over a stream belonging to an older copy.
            window.clockInStopCamera = stopCamera;

            if (!window.clockInCameraBound) {
                window.clockInCameraBound = true;

                $(document).on('hidden.bs.modal', function () {
                    if (typeof window.clockInStopCamera === 'function') {
                        window.clockInStopCamera();
                    }
                });
            }

            if (rules.photo !== 'off') {
                const video = document.getElementById('capture-video');
                const preview = document.getElementById('capture-preview');
                const message = document.getElementById('capture-message');
                const canvas = document.getElementById('capture-canvas');
                const take = document.getElementById('capture-take');
                const again = document.getElementById('capture-again');
                const field = document.getElementById('capture-photo-data');

                function say(text) {
                    message.textContent = text;
                    message.style.display = 'flex';
                    video.style.display = 'none';
                }

                function startCamera() {
                    navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: false })
                        .then(function (granted) {
                            stream = granted;
                            video.srcObject = granted;
                            video.style.display = 'block';
                            message.style.display = 'none';
                            take.style.display = 'inline-block';
                        })
                        .catch(function (error) {
                            say(error && error.name === 'NotAllowedError'
                                ? 'Camera permission was refused.'
                                : 'No camera could be opened.');
                        });
                }

                if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                    say(window.isSecureContext
                        ? 'This browser cannot use the camera.'
                        : 'The camera needs a secure connection (https). Ask for the site to be served over https.');
                } else {
                    startCamera();
                }

                take.addEventListener('click', function () {
                    if (!video.videoWidth) { return; }

                    // Kept small deliberately: this is a record of who was
                    // there, not a photograph worth megabytes on every row.
                    const width = 480;
                    const height = Math.round(video.videoHeight * (width / video.videoWidth));

                    canvas.width = width;
                    canvas.height = height;
                    canvas.getContext('2d').drawImage(video, 0, 0, width, height);

                    field.value = canvas.toDataURL('image/jpeg', 0.7);
                    preview.src = field.value;
                    preview.style.display = 'block';
                    video.style.display = 'none';
                    take.style.display = 'none';
                    again.style.display = 'inline-block';
                    stopCamera();
                });

                again.addEventListener('click', function () {
                    field.value = '';
                    preview.style.display = 'none';
                    again.style.display = 'none';
                    message.textContent = 'Starting the camera…';
                    message.style.display = 'flex';
                    startCamera();
                });
            }

            // ---- where they are ---------------------------------------------
            if (rules.location !== 'off') {
                const status = document.getElementById('place-status');
                const coords = document.getElementById('place-coords');
                const text = document.getElementById('place-text');
                const map = document.getElementById('place-map');
                const latField = document.getElementById('capture-latitude');
                const lngField = document.getElementById('capture-longitude');

                if (!navigator.geolocation) {
                    status.textContent = 'This browser cannot report a location.';
                } else if (!window.isSecureContext) {
                    status.textContent = 'Location needs a secure connection (https).';
                } else {
                    navigator.geolocation.getCurrentPosition(function (position) {
                        const lat = position.coords.latitude;
                        const lng = position.coords.longitude;

                        latField.value = lat;
                        lngField.value = lng;
                        text.textContent = lat.toFixed(6) + ', ' + lng.toFixed(6)
                            + ' (within ' + Math.round(position.coords.accuracy) + ' m)';
                        map.href = 'https://www.google.com/maps/search/?api=1&query=' + lat + ',' + lng;
                        status.style.display = 'none';
                        coords.style.display = 'block';
                    }, function (error) {
                        status.textContent = error && error.code === 1
                            ? 'Location permission was refused.'
                            : 'Your location could not be found.';
                    }, { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 });
                }
            }
        })();
    </script>
@endif
