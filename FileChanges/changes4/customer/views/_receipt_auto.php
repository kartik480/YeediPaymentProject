<?php
$receiptPdfUrl = (string)($receiptPdfUrl ?? '');
$receiptPrintUrl = (string)($receiptPrintUrl ?? '');
$receiptRef = (string)($receiptRef ?? '');
$receiptAuto = !empty($receiptAuto);
if ($receiptPdfUrl === '' || $receiptPrintUrl === '') return;
?>
<script>
window.yeediIssueReceipt = window.yeediIssueReceipt || async function(pdfUrl, printUrl, ref){
  if(!pdfUrl || !printUrl) return;
  var key = 'yeedi-receipt:' + (ref || pdfUrl);
  try { if (sessionStorage.getItem(key)) return; } catch (e) {}
  function openPrint(){
    var frame = document.createElement('iframe');
    frame.title = 'Receipt';
    frame.style.cssText = 'position:fixed;left:0;top:0;width:1px;height:1px;border:0';
    frame.src = printUrl;
    document.body.appendChild(frame);
  }
  var pdfOk = false;
  try {
    var response = await fetch(pdfUrl, {cache:'no-store', credentials:'same-origin'});
    if (response.ok) {
      var blob = await response.blob();
      var type = blob && blob.type ? blob.type : '';
      if (blob && blob.size >= 20 && (type === '' || type.indexOf('pdf') !== -1 || type.indexOf('octet-stream') !== -1)) {
        pdfOk = true;
        var fileUrl = URL.createObjectURL(blob);
        var link = document.createElement('a');
        link.href = fileUrl;
        link.download = (ref || 'payment') + '-payment-success-invoice.pdf';
        document.body.appendChild(link);
        link.click();
        link.remove();
      }
    }
  } catch (e) {}
  openPrint();
  if (pdfOk) { try { sessionStorage.setItem(key, '1'); } catch (e) {} }
};
<?php if ($receiptAuto): ?>
window.yeediIssueReceipt(<?=json_encode($receiptPdfUrl)?>, <?=json_encode($receiptPrintUrl)?>, <?=json_encode($receiptRef)?>);
<?php endif; ?>
</script>
