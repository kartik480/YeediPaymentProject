document.addEventListener('change',e=>{if(e.target&&e.target.id==='bank_qr'&&e.target.files[0])decodeBankQr(e.target.files[0]);});
async function decodeBankQr(file){
  const status=document.getElementById('qrDecodeStatus'); const hidden=document.getElementById('upi_uri');
  if(status) status.textContent='Reading QR…';
  try{
    const bitmap=await createImageBitmap(file);
    let raw='';
    if('BarcodeDetector' in window){
      const detector=new BarcodeDetector({formats:['qr_code']}); const hits=await detector.detect(bitmap);
      if(hits.length && hits[0].rawValue) raw=hits[0].rawValue.trim();
    }
    if(!raw){
      const canvas=document.createElement('canvas'); canvas.width=bitmap.width;canvas.height=bitmap.height;const ctx=canvas.getContext('2d');ctx.drawImage(bitmap,0,0);const img=ctx.getImageData(0,0,canvas.width,canvas.height);
      if(window.jsQR){const code=window.jsQR(img.data,img.width,img.height);if(code&&code.data) raw=code.data.trim();}
    }
    if(raw && hidden){
      hidden.value=raw;
      hidden.dispatchEvent(new Event('input',{bubbles:true}));
      if(status) status.textContent='QR decoded: '+raw;
      return;
    }
    if(status) status.textContent='Could not decode automatically. Paste the exact upi:// URI above, or save the image as-is.';
  }catch(err){if(status) status.textContent='QR read error: '+err.message;}
}
