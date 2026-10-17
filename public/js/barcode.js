/* barcode.js — reads a box's barcode with the camera, for the owner's Barcode buttons: in a game's drawer on the
   shelf, signed in, and beside the Barcode field of a copy's edit page. Barcode.scan({ title, current }) opens the
   camera over the page and resolves with the number read (or typed under it), or null when it is closed.
   The browser's own BarcodeDetector reads it where there is one (Chrome, Android); elsewhere (Safari) ZXing,
   loaded the first time it is needed. A number counts once it is read twice running, so one bad frame can't. */

(function () {
  if (window.Barcode) return;
  const ZXING = 'https://cdn.jsdelivr.net/npm/@zxing/library@0.21.3/umd/index.min.js';
  const FORMATS = ['ean_13', 'ean_8', 'upc_a']; // what games are printed with: EAN in Europe and Australia, UPC in America
  const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);

  // an EAN-8, UPC-A, EAN-13 or GTIN-14 whose last digit checks out (as admin_ui.php's clean_barcode)
  const clean = s => String(s).replace(/[\s-]+/g, '');
  function valid(code) {
    if (!/^(\d{8}|\d{12,14})$/.test(code)) return false;
    const d = [...code].map(Number), check = d.pop();
    const sum = d.reverse().reduce((t, n, i) => t + n * (i % 2 ? 1 : 3), 0);
    return (10 - sum % 10) % 10 === check;
  }

  function load(src) {
    return new Promise((resolve, reject) => {
      const s = document.createElement('script');
      s.src = src;
      s.onload = resolve;
      s.onerror = () => reject(new Error('The barcode reader couldn\'t be loaded: type the number instead.'));
      document.head.append(s);
    });
  }

  // { native, read(source) → the number or null }: the browser's own reader takes the whole frame, ZXing the part in the frame
  let reader = null;
  function makeReader() {
    return reader ||= (async () => {
      if ('BarcodeDetector' in window) {
        try {
          const formats = (await BarcodeDetector.getSupportedFormats()).filter(f => FORMATS.includes(f));
          if (formats.length) {
            const d = new BarcodeDetector({ formats });
            return { native: true, read: async src => (await d.detect(src)).find(b => valid(b.rawValue))?.rawValue || null };
          }
        } catch (e) { /* there, but not for these: ZXing instead */ }
      }
      if (!window.ZXing) await load(ZXING);
      const Z = window.ZXing, F = Z.BarcodeFormat;
      const r = new Z.MultiFormatReader();
      r.setHints(new Map([[Z.DecodeHintType.POSSIBLE_FORMATS, [F.EAN_13, F.EAN_8, F.UPC_A]], [Z.DecodeHintType.TRY_HARDER, true]]));
      return {
        native: false,
        read: async canvas => {
          try { return r.decodeWithState(new Z.BinaryBitmap(new Z.HybridBinarizer(new Z.HTMLCanvasElementLuminanceSource(canvas)))).getText(); } catch (e) { return null; } finally { r.reset(); }
        },
      };
    })().catch(e => { reader = null; throw e; });
  }

  const CSS = `
    .bc-scan { position: fixed; inset: 0; z-index: 2147483000; display: flex; flex-direction: column; background: #0b0b0d; color: #f4f2ee; font: 15px/1.4 system-ui, -apple-system, sans-serif; }
    .bc-top { display: flex; align-items: center; gap: 12px; padding: max(12px, env(safe-area-inset-top)) 16px 12px; }
    .bc-top div { flex: 1; min-width: 0; }
    .bc-top b { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 16px; }
    .bc-top small { opacity: .65; }
    .bc-scan button { padding: 9px 14px; border: 1px solid rgba(255,255,255,.28); border-radius: 99px; background: none; color: inherit; font: inherit; cursor: pointer; }
    .bc-scan button.bc-x { width: 40px; height: 40px; padding: 0; font-size: 18px; }
    .bc-scan button[aria-pressed="true"] { border-color: #f5c542; color: #f5c542; }
    .bc-view { position: relative; flex: 1; min-height: 0; overflow: hidden; background: #000; }
    .bc-view video { width: 100%; height: 100%; object-fit: cover; display: block; }
    .bc-frame { position: absolute; left: 50%; top: 50%; width: min(84%, 520px); height: min(38%, 240px); transform: translate(-50%, -50%); border-radius: 14px;
      box-shadow: 0 0 0 100vmax rgba(0,0,0,.45); outline: 2px solid rgba(255,255,255,.9); }
    .bc-frame::after { content: ""; position: absolute; left: 6%; right: 6%; top: 50%; height: 2px; background: #ff4d4d; box-shadow: 0 0 8px #ff4d4d; opacity: .8; }
    .bc-scan.got .bc-frame { outline-color: #4ade80; }
    .bc-msg { position: absolute; left: 16px; right: 16px; bottom: 16px; margin: 0; text-align: center; text-shadow: 0 1px 3px #000; }
    .bc-msg:empty { display: none; }
    .bc-type { display: flex; gap: 8px; padding: 12px 16px max(12px, env(safe-area-inset-bottom)); }
    .bc-type input { flex: 1; min-width: 0; padding: 10px 14px; border: 1px solid rgba(255,255,255,.28); border-radius: 99px; background: rgba(255,255,255,.06); color: inherit; font: inherit; font-size: 16px; letter-spacing: .04em; }
    .bc-type input[aria-invalid="true"] { border-color: #ff6b6b; }`;

  function scan({ title = '', current = null } = {}) {
    if (!document.getElementById('bc-css')) document.head.insertAdjacentHTML('beforeend', `<style id="bc-css">${CSS}</style>`);
    const el = document.createElement('div');
    el.className = 'bc-scan';
    el.setAttribute('role', 'dialog');
    el.setAttribute('aria-modal', 'true');
    el.setAttribute('aria-label', 'Scan the barcode');
    el.innerHTML = `
      <div class="bc-top"><div><b>${esc(title || 'Scan the barcode')}</b><small>${current ? `Now ${esc(current)}. ` : ''}Hold the barcode across the line.</small></div>
        <button type="button" class="bc-light" hidden aria-pressed="false">Light</button>
        <button type="button" class="bc-x" aria-label="Close">✕</button></div>
      <div class="bc-view"><video playsinline muted autoplay></video><div class="bc-frame"></div><p class="bc-msg" aria-live="polite">Starting the camera…</p></div>
      <form class="bc-type"><input inputmode="numeric" autocomplete="off" enterkeyhint="done" placeholder="Or type the number under it" aria-label="The number under the barcode"><button type="submit">Save</button></form>`;
    document.body.append(el);
    const video = el.querySelector('video'), msg = el.querySelector('.bc-msg'), frame = el.querySelector('.bc-frame');
    const input = el.querySelector('input'), light = el.querySelector('.bc-light');
    const canvas = document.createElement('canvas'), ctx = canvas.getContext('2d', { willReadFrequently: true });
    const say = t => { msg.textContent = t; };
    let stream = null, done = false, finish;

    /* what ZXing reads: the part of the frame inside the box drawn over it, a little more round it (the video is
       cropped to cover its box), or the whole frame, for a barcode held closer than the box */
    function grab(whole) {
      const vw = video.videoWidth, vh = video.videoHeight;
      let x = 0, y = 0, w = vw, h = vh;
      if (!whole) {
        const v = video.getBoundingClientRect(), f = frame.getBoundingClientRect();
        const s = Math.max(v.width / vw, v.height / vh), ox = (v.width - vw * s) / 2, oy = (v.height - vh * s) / 2, pad = 0.12 * f.width;
        x = Math.max(0, (f.left - v.left - pad - ox) / s);
        y = Math.max(0, (f.top - v.top - pad - oy) / s);
        w = Math.min(vw - x, (f.width + 2 * pad) / s);
        h = Math.min(vh - y, (f.height + 2 * pad) / s);
      }
      const k = Math.min(1, 1000 / Math.max(w, h)); // ZXing is quick enough at about 1000 across
      canvas.width = Math.round(w * k);
      canvas.height = Math.round(h * k);
      ctx.drawImage(video, x, y, w, h, 0, 0, canvas.width, canvas.height);
      return canvas;
    }

    async function run() {
      if (!navigator.mediaDevices?.getUserMedia) return say('The camera needs the site over HTTPS. Type the number instead.');
      try {
        stream = await navigator.mediaDevices.getUserMedia({ audio: false, video: { facingMode: { ideal: 'environment' }, width: { ideal: 1920 }, height: { ideal: 1080 } } });
      } catch (e) {
        return say(e.name === 'NotAllowedError' ? 'The camera wasn\'t allowed: allow it in the browser\'s settings, or type the number.'
          : e.name === 'NotFoundError' ? 'There is no camera here: type the number.' : `The camera didn't start (${e.message}). Type the number instead.`);
      }
      if (done) return stop();
      video.srcObject = stream;
      await video.play().catch(() => {});
      const track = stream.getVideoTracks()[0];
      if (track.getCapabilities?.().torch) {
        light.hidden = false;
        light.onclick = () => {
          const on = light.getAttribute('aria-pressed') !== 'true';
          track.applyConstraints({ advanced: [{ torch: on }] }).then(() => light.setAttribute('aria-pressed', on)).catch(() => {});
        };
      }
      let read;
      try { read = await makeReader(); } catch (e) { return say(e.message); }
      say('');
      let last = null, n = 0;
      while (!done) {
        if (video.readyState >= 2 && video.videoWidth) {
          const code = await read.read(read.native ? video : grab(n++ % 3 === 2)).catch(() => null);
          if (code && code === last && valid(code)) {
            el.classList.add('got');
            say(code);
            navigator.vibrate?.(60);
            await new Promise(r => setTimeout(r, 350));
            return finish(code);
          }
          last = code || last;
        }
        await new Promise(r => setTimeout(r, read.native ? 120 : 60));
      }
    }

    function stop() {
      stream?.getTracks().forEach(t => t.stop());
      stream = null;
    }

    // Escape closes this, not the drawer under it
    const onKey = e => { if (e.key === 'Escape') { e.stopImmediatePropagation(); e.preventDefault(); finish(null); } };
    addEventListener('keydown', onKey, true);

    return new Promise(resolve => {
      finish = code => {
        if (done) return;
        done = true;
        stop();
        removeEventListener('keydown', onKey, true);
        el.remove();
        resolve(code);
      };
      el.querySelector('.bc-x').addEventListener('click', () => finish(null));
      el.querySelector('form').addEventListener('submit', e => {
        e.preventDefault();
        const code = clean(input.value);
        if (valid(code)) return finish(code);
        input.setAttribute('aria-invalid', 'true');
        say(/^\d+$/.test(code) && [8, 12, 13, 14].includes(code.length) ? `${code} doesn't check out: a digit is mistyped.` : 'The number under a barcode has 8, 12 or 13 digits.');
      });
      input.addEventListener('input', () => input.removeAttribute('aria-invalid'));
      run();
    });
  }

  window.Barcode = { scan, valid };
})();
