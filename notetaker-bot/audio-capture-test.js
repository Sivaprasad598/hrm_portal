// Diagnostic: does remote WebRTC audio reach the bot's recorder in this Chrome?
// Two RTCPeerConnections in one page send a 440 Hz tone to each other; the received (remote) track is then
// measured the way bot.js records it (Web Audio via a muted <audio> element) and through two alternatives.
// Run: node audio-capture-test.js     (uses the installed Chrome, a throwaway profile, no Google sign-in)

const { chromium } = require('playwright-core');

(async () => {
  const browser = await chromium.launch({
    channel: 'chrome',
    headless: false,
    args: ['--autoplay-policy=no-user-gesture-required', '--window-position=-2400,0',
      '--disable-backgrounding-occluded-windows', '--disable-renderer-backgrounding'],
  });
  const page = await browser.newPage();
  await page.goto('about:blank');

  const result = await page.evaluate(async () => {
    const out = { chrome: navigator.userAgent.match(/Chrome\/[\d.]+/)?.[0] };

    // Sender: an oscillator as a local audio track
    const sendCtx = new AudioContext();
    const osc = sendCtx.createOscillator();
    const sendDest = sendCtx.createMediaStreamDestination();
    osc.connect(sendDest);
    osc.start();

    const a = new RTCPeerConnection();
    const b = new RTCPeerConnection();
    a.onicecandidate = (e) => e.candidate && b.addIceCandidate(e.candidate);
    b.onicecandidate = (e) => e.candidate && a.addIceCandidate(e.candidate);
    const remote = new Promise((resolve) => { b.ontrack = (e) => resolve(e.track); });
    a.addTrack(sendDest.stream.getAudioTracks()[0], sendDest.stream);
    await a.setLocalDescription(await a.createOffer());
    await b.setRemoteDescription(a.localDescription);
    await b.setLocalDescription(await b.createAnswer());
    await a.setRemoteDescription(b.localDescription);
    const track = await remote;
    await new Promise((r) => setTimeout(r, 2000));
    out.track = { muted: track.muted, readyState: track.readyState };

    const peakOf = async (node, ctx) => {
      const an = ctx.createAnalyser();
      an.fftSize = 2048;
      node.connect(an);
      const buf = new Float32Array(an.fftSize);
      let peak = 0;
      for (let i = 0; i < 12; i++) {
        await new Promise((r) => setTimeout(r, 250));
        an.getFloatTimeDomainData(buf);
        for (const v of buf) peak = Math.max(peak, Math.abs(v));
      }
      return Math.round(peak * 1000) / 10; // %
    };

    // 1. Current bot.js method: stream on a MUTED <audio> element + Web Audio source
    {
      const el = new Audio(); el.srcObject = new MediaStream([track]); el.muted = true; el.play().catch(() => {});
      const ctx = new AudioContext(); await ctx.resume();
      out.mutedElementWebAudio = await peakOf(ctx.createMediaStreamSource(new MediaStream([track])), ctx);
    }
    // 2. Same, element unmuted at volume 0
    {
      const el = new Audio(); el.srcObject = new MediaStream([track]); el.volume = 0; el.play().catch(() => {});
      const ctx = new AudioContext(); await ctx.resume();
      out.volume0ElementWebAudio = await peakOf(ctx.createMediaStreamSource(new MediaStream([track])), ctx);
    }
    // 3. Re-made as a local track: MediaStreamTrackProcessor -> MediaStreamTrackGenerator -> Web Audio
    if (window.MediaStreamTrackProcessor && window.MediaStreamTrackGenerator) {
      const processor = new MediaStreamTrackProcessor({ track: track.clone() });
      const generator = new MediaStreamTrackGenerator({ kind: 'audio' });
      processor.readable.pipeTo(generator.writable).catch(() => {});
      const ctx = new AudioContext(); await ctx.resume();
      out.processorGenerator = await peakOf(ctx.createMediaStreamSource(new MediaStream([generator])), ctx);
    } else {
      out.processorGenerator = 'not available in this Chrome';
    }
    return out;
  });

  console.log(JSON.stringify(result, null, 2));
  await browser.close();
})().catch((e) => { console.error(e); process.exit(1); });
