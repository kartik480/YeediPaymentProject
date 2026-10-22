async function decodeBankQr(file){
  const status=document.getElementById('qrDecodeStatus'); const hidden=document.getElementById('upi_uri');
  if(!status||!hidden) return;
  status.textContent='Reading QR…';
  try{
    const bitmap=await createImageBitmap(file);
    if('BarcodeDetector' in window){
      const detector=new BarcodeDetector({formats:['qr_code']}); const hits=await detector.detect(bitmap);
      if(hits.length && hits[0].rawValue){hidden.value=hits[0].rawValue.trim();status.textContent='QR decoded.';return;}
    }
    const canvas=document.createElement('canvas'); canvas.width=bitmap.width;canvas.height=bitmap.height;const ctx=canvas.getContext('2d');ctx.drawImage(bitmap,0,0);const img=ctx.getImageData(0,0,canvas.width,canvas.height);
    if(window.jsQR){const code=window.jsQR(img.data,img.width,img.height);if(code&&code.data){hidden.value=code.data.trim();status.textContent='QR decoded.';return;}}
    status.textContent='Could not decode automatically. Enter UPI VPA and Payee Name manually.';
  }catch(e){status.textContent='QR read error: '+e.message;}
}
document.addEventListener('change',e=>{if(e.target&&e.target.id==='bank_qr'&&e.target.files[0])decodeBankQr(e.target.files[0]);});
