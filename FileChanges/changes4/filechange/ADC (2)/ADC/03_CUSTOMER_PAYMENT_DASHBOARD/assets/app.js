(() => {
  'use strict';
  const cfg = window.YEEDI_CONFIG || {};
  const apiBase = String(cfg.apiBase || '').replace(/\/$/, '');
  const hashParams = new URLSearchParams(location.hash.replace(/^#/, ''));
  const queryParams = new URLSearchParams(location.search);
  const paymentId = hashParams.get('payment_id') || queryParams.get('payment_id') || '';
  const token = hashParams.get('token') || queryParams.get('token') || '';
  const defaultPollMs = Math.max(1000, Number(cfg.pollSeconds || 2) * 1000);

  const $ = id => document.getElementById(id);
  let pollTimer = null;
  let countdownTimer = null;
  let lastData = null;

  function show(id, visible=true){ $(id).classList.toggle('hidden', !visible); }
  function money(v){ return '₹' + Number(v || 0).toFixed(2); }
  function text(id, value){ $(id).textContent = value == null || value === '' ? '—' : String(value); }

  function schedulePoll(seconds){
    if (pollTimer) clearTimeout(pollTimer);
    const ms = Math.max(1000, Number(seconds || 0) * 1000 || defaultPollMs);
    pollTimer = setTimeout(async () => {
      try { await getStatus(); } catch (_) {}
      if (!lastData || lastData.keep_polling !== false) {
        schedulePoll(lastData?.recommended_poll_seconds || cfg.pollSeconds || 2);
      }
    }, ms);
  }

  function startLocalCountdown(seconds){
    if (countdownTimer) clearInterval(countdownTimer);
    let remaining = Math.max(0, Number(seconds || 0));
    text('verifyCountdown', remaining > 0 ? `${remaining}s` : '');
    updateProgress(remaining, Number(lastData?.verification_buffer_seconds || 30));
    countdownTimer = setInterval(() => {
      remaining = Math.max(0, remaining - 1);
      text('verifyCountdown', remaining > 0 ? `${remaining}s` : '');
      updateProgress(remaining, Number(lastData?.verification_buffer_seconds || 30));
      if (remaining <= 0) clearInterval(countdownTimer);
    }, 1000);
  }

  function updateProgress(remaining, total){
    const t = Math.max(1, total || 30);
    const elapsed = Math.min(t, Math.max(0, t - remaining));
    $('progressBar').style.width = `${Math.round((elapsed / t) * 100)}%`;
  }

  async function getStatus(){
    if (!paymentId || !token || !apiBase || apiBase.includes('YOUR-DOMAIN')) {
      text('message', 'Missing payment link configuration.');
      return null;
    }
    const r = await fetch(`${apiBase}/api/payments/status.php`, {
      method:'POST', headers:{'Content-Type':'application/json'}, credentials:'omit', cache:'no-store',
      body: JSON.stringify({payment_id:paymentId, token})
    });
    const data = await r.json().catch(() => ({ok:false,error:'invalid_server_response'}));
    if (!r.ok || !data.ok) throw new Error(data.error || `HTTP ${r.status}`);
    lastData = data;
    render(data);
    return data;
  }

  function render(d){
    show('paymentBox', true);
    text('amount', money(d.amount));
    text('bank', d.bank);
    text('account', d.account_label + (d.account_last4 ? ` ••••${d.account_last4}` : ''));
    text('upiId', d.upi_id);
    text('statusText', d.status);
    text('orderTimer', d.expires_in_seconds > 0 ? `Order ${d.expires_in_seconds}s` : '');

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
      text('message', 'Payment submitted. Verifying now…');
      text('verifyTitle', 'Verifying payment');
      text('verifyMessage', d.customer_message || 'Checking bank confirmation. Please do not refresh.');
      $('verificationBox').classList.remove('delayed');
      startLocalCountdown(d.verification_remaining_seconds || 0);
      $('paidButton').disabled = true;
      $('paidButton').textContent = 'VERIFYING PAYMENT…';
      $('upiButton').classList.add('disabled-link');
      $('dot').classList.add('pulse');
    } else if (state === 'WAITING_BANK_SMS') {
      text('message', 'Bank confirmation is taking a little longer.');
      text('verifyTitle', 'Waiting for bank SMS confirmation');
      text('verifyMessage', d.customer_message || 'We are continuing verification. Please do not pay again.');
      text('verifyCountdown', 'Still checking');
      updateProgress(0, 30);
      $('progressBar').style.width = '100%';
      $('verificationBox').classList.add('delayed');
      $('paidButton').disabled = true;
      $('paidButton').textContent = 'PAYMENT SUBMITTED';
      $('upiButton').classList.add('disabled-link');
      $('dot').classList.add('pulse');
    } else if (state === 'REVIEW_REQUIRED') {
      text('message', 'Payment needs verification review. Do not pay again.');
      $('paidButton').disabled = true;
      $('paidButton').textContent = 'PAYMENT UNDER REVIEW';
      $('upiButton').classList.add('disabled-link');
      $('dot').classList.remove('pulse');
    } else if (state === 'EXPIRED') {
      text('message', d.customer_message || 'Payment window expired.');
      $('dot').classList.remove('pulse');
    } else if (state === 'CONFIRMED') {
      text('message', 'Payment confirmed successfully.');
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
      text('message', d.customer_message || 'Complete payment and tap I HAVE PAID.');
      $('paidButton').disabled = false;
      $('paidButton').textContent = 'I HAVE PAID — CHECK NOW';
      $('upiButton').classList.remove('disabled-link');
      $('dot').classList.remove('pulse');
    }
  }

  async function acknowledge(){
    if (!confirm('If you have completed the UPI payment, tap OK. Do not pay again after this verification starts.')) return;
    $('paidButton').disabled = true;
    $('paidButton').textContent = 'STARTING VERIFICATION…';
    text('message', 'Connecting to payment server…');
    try {
      const r = await fetch(`${apiBase}/api/payments/acknowledge.php`, {
        method:'POST', headers:{'Content-Type':'application/json'}, credentials:'omit', cache:'no-store',
        body: JSON.stringify({payment_id:paymentId, token})
      });
      const data = await r.json().catch(() => ({ok:false,error:'invalid_server_response'}));
      if (!r.ok || !data.ok) throw new Error(data.error || `HTTP ${r.status}`);
      lastData = data;
      render(data);
      schedulePoll(data.recommended_poll_seconds || cfg.pollSeconds || 2);
    } catch (e) {
      text('message', 'Server check could not start. Automatic status checking will continue.');
      $('paidButton').disabled = false;
      $('paidButton').textContent = 'I HAVE PAID — CHECK NOW';
    }
  }

  $('paidButton').addEventListener('click', acknowledge);
  getStatus()
    .then(d => { if (d && d.keep_polling !== false) schedulePoll(d.recommended_poll_seconds || cfg.pollSeconds || 2); })
    .catch(e => { text('message', `Payment check error: ${e.message}`); schedulePoll(cfg.pollSeconds || 2); });
})();
