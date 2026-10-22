(() => {
  'use strict';
  const cfg = window.YEEDI_CONFIG || {};
  const apiBase = String(cfg.apiBase || '').replace(/\/$/, '');
  const hashParams = new URLSearchParams(location.hash.replace(/^#/, ''));
  const queryParams = new URLSearchParams(location.search);

  const clientId = hashParams.get('client_id') || queryParams.get('client_id') || '';
  let paymentId = hashParams.get('payment_id') || queryParams.get('payment_id') || '';
  let token = hashParams.get('token') || queryParams.get('token') || '';
  const urlName = hashParams.get('name') || queryParams.get('name') || '';
  const urlCid = hashParams.get('cid') || queryParams.get('cid') || '';

  const defaultPollMs = Math.max(1000, Number(cfg.pollSeconds || 2) * 1000);

  const $ = id => document.getElementById(id);
  let pollTimer = null;
  let countdownTimer = null;
  let lastData = null;
  let selectedAmount = 0;

  function show(id, visible) { $(id).classList.toggle('hidden', !visible); }
  function money(v) { return '₹' + Number(v || 0).toFixed(2); }
  function text(id, value) { $(id).textContent = value == null || value === '' ? '—' : String(value); }

  function applyTheme(color) {
    if (!color || !/^#[0-9a-fA-F]{6}$/.test(color)) return;
    document.documentElement.style.setProperty('--theme', color);
    const r = parseInt(color.slice(1,3),16), g = parseInt(color.slice(3,5),16), b = parseInt(color.slice(5,7),16);
    const darker = '#' + [r,g,b].map(c => Math.max(0, c - 30).toString(16).padStart(2,'0')).join('');
    const lighter = '#' + [r,g,b].map(c => Math.min(255, c + 180).toString(16).padStart(2,'0')).join('');
    document.documentElement.style.setProperty('--theme-dark', darker);
    document.documentElement.style.setProperty('--theme-light', lighter);
  }

  function applyMerchantBranding(m) {
    if (!m) return;
    if (m.logo_url) {
      $('merchantLogo').src = m.logo_url;
      $('merchantLogo').alt = m.name || m.display_name || '';
    }
    text('merchantName', m.name || m.display_name || m.client_name || '');
    if (m.name || m.display_name || m.logo_url) show('merchantHeader', true);
    if (m.theme_color) applyTheme(m.theme_color);
    document.title = (m.name || m.display_name || 'Payment') + ' - Payment';

    if (m.video_url) {
      $('guideVideo').src = m.video_url;
      show('videoPanel', true);
    }

    const hasWhatsapp = m.support_whatsapp && m.support_whatsapp.trim();
    const hasEmail = m.support_email && m.support_email.trim();
    if (hasWhatsapp || hasEmail) {
      show('supportSection', true);
      if (hasWhatsapp) {
        const num = m.support_whatsapp.replace(/\D/g, '');
        $('supportWhatsappLink').href = 'https://wa.me/' + num;
        $('supportWhatsappLink').textContent = m.support_whatsapp;
      } else {
        $('whatsappCard').style.display = 'none';
      }
      if (hasEmail) {
        $('supportEmailLink').href = 'mailto:' + m.support_email;
        $('supportEmailLink').textContent = m.support_email;
      } else {
        $('emailCard').style.display = 'none';
      }
    }
  }

  function buildQuickAmounts(amounts) {
    const container = $('quickAmounts');
    container.innerHTML = '';
    (amounts || [100,500,1000,2000,5000,10000,25000,50000]).forEach(amt => {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'quick-btn';
      btn.textContent = '₹' + amt.toLocaleString('en-IN');
      btn.addEventListener('click', () => {
        selectedAmount = amt;
        $('amountInput').value = amt;
        updateAmountDisplay(amt);
        container.querySelectorAll('.quick-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
      });
      container.appendChild(btn);
    });
  }

  function updateAmountDisplay(val) {
    const num = Number(val) || 0;
    text('amountDisplay', 'INR ' + num.toFixed(2));
  }

  function showCustomerInfo(name, cid) {
    if (name || cid) {
      text('customerName', name || '');
      text('customerId', cid || '');
      show('customerInfo', true);
    }
  }

  /* ---- Self-service flow (client_id) ---- */
  async function initMerchantFlow() {
    if (!apiBase || apiBase.includes('YOUR-DOMAIN')) {
      text('globalMessage', 'Payment service is not configured.');
      return;
    }
    try {
      const r = await fetch(apiBase + '/api/payments/merchant.php?client_id=' + encodeURIComponent(clientId), {
        credentials: 'omit', cache: 'no-store'
      });
      const data = await r.json().catch(() => ({ ok: false, error: 'invalid_server_response' }));
      if (!r.ok || !data.ok) throw new Error(data.error || 'merchant_not_found');

      applyMerchantBranding(data);
      buildQuickAmounts(data.quick_amounts);
      showCustomerInfo(urlName, urlCid);

      $('amountInput').addEventListener('input', () => {
        const v = Number($('amountInput').value) || 0;
        selectedAmount = v;
        updateAmountDisplay(v);
        $('quickAmounts').querySelectorAll('.quick-btn').forEach(b => b.classList.remove('active'));
      });

      show('loadingState', false);
      show('mainContent', true);
      show('amountPhase', true);

    } catch (e) {
      text('globalMessage', 'Could not load merchant: ' + e.message);
    }
  }

  async function handlePay() {
    const amount = Number($('amountInput').value);
    if (!amount || amount <= 0) {
      alert('Please enter a valid amount.');
      return;
    }
    $('payButton').disabled = true;
    $('payButton').textContent = 'Creating payment…';

    try {
      const r = await fetch(apiBase + '/api/payments/public_create.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'omit',
        cache: 'no-store',
        body: JSON.stringify({
          client_id: clientId,
          amount: String(amount),
          customer_name: urlName,
          customer_id: urlCid
        })
      });
      const data = await r.json().catch(() => ({ ok: false, error: 'invalid_server_response' }));
      if (!r.ok || !data.ok) throw new Error(data.error || 'Payment creation failed');

      paymentId = data.payment_id;
      token = data.public_token;

      show('amountPhase', false);
      show('paymentPhase', true);
      renderPayment(data);
      schedulePoll(data.recommended_poll_seconds || cfg.pollSeconds || 2);
    } catch (e) {
      alert('Error: ' + e.message);
      $('payButton').disabled = false;
      $('payButton').textContent = 'Pay';
    }
  }

  /* ---- Direct payment flow (payment_id + token) ---- */
  async function initDirectFlow() {
    try {
      const data = await getStatus();
      if (data) {
        if (data.merchant) applyMerchantBranding(data.merchant);
        showCustomerInfo(data.customer_name_display, data.customer_display_id);
        show('loadingState', false);
        show('mainContent', true);
        show('amountPhase', false);
        show('paymentPhase', true);
        if (data.keep_polling !== false) {
          schedulePoll(data.recommended_poll_seconds || cfg.pollSeconds || 2);
        }
      }
    } catch (e) {
      text('globalMessage', 'Payment check error: ' + e.message);
    }
  }

  /* ---- Status polling ---- */
  function schedulePoll(seconds) {
    if (pollTimer) clearTimeout(pollTimer);
    const ms = Math.max(1000, Number(seconds || 0) * 1000 || defaultPollMs);
    pollTimer = setTimeout(async () => {
      try { await getStatus(); } catch (_) {}
      if (!lastData || lastData.keep_polling !== false) {
        schedulePoll(lastData?.recommended_poll_seconds || cfg.pollSeconds || 2);
      }
    }, ms);
  }

  function startLocalCountdown(seconds) {
    if (countdownTimer) clearInterval(countdownTimer);
    let remaining = Math.max(0, Number(seconds || 0));
    text('verifyCountdown', remaining > 0 ? remaining + 's' : '');
    updateProgress(remaining, Number(lastData?.verification_buffer_seconds || 30));
    countdownTimer = setInterval(() => {
      remaining = Math.max(0, remaining - 1);
      text('verifyCountdown', remaining > 0 ? remaining + 's' : '');
      updateProgress(remaining, Number(lastData?.verification_buffer_seconds || 30));
      if (remaining <= 0) clearInterval(countdownTimer);
    }, 1000);
  }

  function updateProgress(remaining, total) {
    const t = Math.max(1, total || 30);
    const elapsed = Math.min(t, Math.max(0, t - remaining));
    $('progressBar').style.width = Math.round((elapsed / t) * 100) + '%';
  }

  async function getStatus() {
    if (!paymentId || !token || !apiBase) {
      text('globalMessage', 'Missing payment link.');
      return null;
    }
    const r = await fetch(apiBase + '/api/payments/status.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'omit',
      cache: 'no-store',
      body: JSON.stringify({ payment_id: paymentId, token: token })
    });
    const data = await r.json().catch(() => ({ ok: false, error: 'invalid_server_response' }));
    if (!r.ok || !data.ok) throw new Error(data.error || 'HTTP ' + r.status);
    lastData = data;
    renderPayment(data);
    return data;
  }

  function renderPayment(d) {
    text('pAmount', money(d.amount));
    text('pBank', d.bank);
    text('pAccount', d.account_label + (d.account_last4 ? ' ••••' + d.account_last4 : ''));
    text('pUpiId', d.upi_id);
    text('statusText', d.status);
    text('orderTimer', d.expires_in_seconds > 0 ? 'Order ' + d.expires_in_seconds + 's' : '');

    if (d.qr_image_url) {
      $('qrImage').src = d.qr_image_url;
      show('qrWrap', true);
    } else show('qrWrap', false);

    if (d.upi_uri) {
      $('upiButton').href = d.upi_uri;
      show('upiButton', true);
    } else show('upiButton', false);

    show('successBox', d.status === 'SUCCESS');
    show('reviewBox', d.status === 'REVIEW_REQUIRED');
    show('expiredBox', d.status === 'EXPIRED');

    const state = d.verification_state || 'AWAITING_PAYMENT';
    const acknowledged = !!d.customer_acknowledged;
    show('verificationBox', acknowledged && d.status !== 'SUCCESS');
    show('initialInstruction', !acknowledged && d.status === 'PENDING');

    if (state === 'VERIFYING') {
      text('verifyTitle', 'Verifying payment');
      text('verifyMessage', d.customer_message || 'Checking bank confirmation. Please do not refresh.');
      $('verificationBox').classList.remove('delayed');
      startLocalCountdown(d.verification_remaining_seconds || 0);
      $('paidButton').disabled = true;
      $('paidButton').textContent = 'VERIFYING PAYMENT…';
      $('upiButton').classList.add('disabled-link');
      $('dot').classList.add('pulse');
    } else if (state === 'WAITING_BANK_SMS') {
      text('verifyTitle', 'Waiting for bank SMS confirmation');
      text('verifyMessage', d.customer_message || 'We are continuing verification. Please do not pay again.');
      text('verifyCountdown', 'Still checking');
      $('progressBar').style.width = '100%';
      $('verificationBox').classList.add('delayed');
      $('paidButton').disabled = true;
      $('paidButton').textContent = 'PAYMENT SUBMITTED';
      $('upiButton').classList.add('disabled-link');
      $('dot').classList.add('pulse');
    } else if (state === 'REVIEW_REQUIRED') {
      $('paidButton').disabled = true;
      $('paidButton').textContent = 'PAYMENT UNDER REVIEW';
      $('upiButton').classList.add('disabled-link');
      $('dot').classList.remove('pulse');
    } else if (state === 'EXPIRED') {
      $('dot').classList.remove('pulse');
    } else if (state === 'CONFIRMED') {
      show('verificationBox', false);
      text('utr', d.utr);
      text('paidAt', d.paid_at);
      $('paidButton').disabled = true;
      $('paidButton').textContent = 'PAYMENT SUCCESSFUL';
      $('upiButton').classList.add('disabled-link');
      $('dot').classList.remove('pulse');
      $('dot').classList.add('success-dot');
      if (pollTimer) clearTimeout(pollTimer);
      if (countdownTimer) clearInterval(countdownTimer);
    } else {
      $('paidButton').disabled = false;
      $('paidButton').textContent = 'I HAVE PAID — CHECK NOW';
      $('upiButton').classList.remove('disabled-link');
      $('dot').classList.remove('pulse');
    }
  }

  async function acknowledge() {
    if (!confirm('If you have completed the UPI payment, tap OK. Do not pay again after verification starts.')) return;
    $('paidButton').disabled = true;
    $('paidButton').textContent = 'STARTING VERIFICATION…';
    try {
      const r = await fetch(apiBase + '/api/payments/acknowledge.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'omit',
        cache: 'no-store',
        body: JSON.stringify({ payment_id: paymentId, token: token })
      });
      const data = await r.json().catch(() => ({ ok: false, error: 'invalid_server_response' }));
      if (!r.ok || !data.ok) throw new Error(data.error || 'HTTP ' + r.status);
      lastData = data;
      renderPayment(data);
      schedulePoll(data.recommended_poll_seconds || cfg.pollSeconds || 2);
    } catch (e) {
      $('paidButton').disabled = false;
      $('paidButton').textContent = 'I HAVE PAID — CHECK NOW';
    }
  }

  /* ---- Init ---- */
  $('payButton').addEventListener('click', handlePay);
  $('paidButton').addEventListener('click', acknowledge);

  if (clientId) {
    initMerchantFlow();
  } else if (paymentId && token) {
    initDirectFlow();
  } else {
    text('globalMessage', 'Invalid payment link. Missing client_id or payment_id.');
  }
})();
