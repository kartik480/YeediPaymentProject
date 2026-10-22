(function (w) {
  function logoSrc(el) {
    return (el && el.getAttribute('data-logo')) || '/image/yeedi.png';
  }
  function placeLogo(el) {
    if (!el || el.querySelector('img.qr-brand')) return;
    var img = document.createElement('img');
    img.className = 'qr-brand';
    img.alt = 'YEEDI';
    img.src = logoSrc(el);
    el.appendChild(img);
  }
  w.yeediQr = function (el, text, size) {
    if (!el || !w.QRCode) return;
    el.innerHTML = '';
    var n = size || 220;
    new QRCode(el, {
      text: String(text || ''),
      width: n,
      height: n,
      correctLevel: QRCode.CorrectLevel.H,
      colorDark: '#111111',
      colorLight: '#ffffff'
    });
    placeLogo(el);
  };
  w.yeediQrDownload = function (el, filename) {
    if (!el) return;
    var canvas = el.querySelector('canvas');
    var logo = el.querySelector('img.qr-brand');
    var a = document.createElement('a');
    a.download = filename || 'yeedi-qr.png';
    function save(href) { a.href = href; a.click(); }
    if (!canvas) {
      var fallback = el.querySelector('img:not(.qr-brand)');
      if (fallback) save(fallback.src);
      return;
    }
    var out = document.createElement('canvas');
    out.width = canvas.width;
    out.height = canvas.height;
    var ctx = out.getContext('2d');
    ctx.drawImage(canvas, 0, 0);
    function stamp() {
      if (logo && logo.naturalWidth) {
        var maxW = out.width * 0.48;
        var maxH = out.height * 0.28;
        var ratio = logo.naturalWidth / Math.max(1, logo.naturalHeight);
        var bw = maxW;
        var bh = bw / ratio;
        if (bh > maxH) { bh = maxH; bw = bh * ratio; }
        var x = (out.width - bw) / 2;
        var y = (out.height - bh) / 2;
        var pad = Math.max(5, out.width * 0.02);
        ctx.fillStyle = '#ffffff';
        if (ctx.roundRect) {
          ctx.beginPath();
          ctx.roundRect(x - pad, y - pad, bw + pad * 2, bh + pad * 2, 10);
          ctx.fill();
        } else {
          ctx.fillRect(x - pad, y - pad, bw + pad * 2, bh + pad * 2);
        }
        ctx.drawImage(logo, x, y, bw, bh);
      }
      save(out.toDataURL('image/png'));
    }
    if (logo && !logo.complete) {
      logo.onload = stamp;
      logo.onerror = function () { save(canvas.toDataURL('image/png')); };
    } else {
      stamp();
    }
  };
})(window);
