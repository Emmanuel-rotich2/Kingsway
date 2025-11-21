<?php
function renderQRCodeModal() {
?>
<!-- QR Code Modal -->
<div class="modal fade" id="qrCodeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Student QR Code</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <img id="studentQRCode" src="" alt="Student QR Code" class="img-fluid">
                <div class="mt-3">
                    <h6 id="studentName"></h6>
                    <p id="studentAdmNo"></p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="printQRCode()">Print</button>
            </div>
        </div>
    </div>
</div>

<script>
function printQRCode() {
    const printWindow = window.open('', '', 'width=600,height=600');
    const qrCode = document.getElementById('studentQRCode').src;
    const studentName = document.getElementById('studentName').textContent;
    const admNo = document.getElementById('studentAdmNo').textContent;
    
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Student QR Code</title>
            <style>
                body { 
                    display: flex; 
                    flex-direction: column;
                    justify-content: center; 
                    align-items: center; 
                    height: 100vh; 
                    margin: 0; 
                }
                img { max-width: 300px; }
                .details { text-align: center; margin-top: 20px; }
            </style>
        </head>
        <body>
            <img src="${qrCode}" onload="window.print(); window.close();">
            <div class="details">
                <h3>${studentName}</h3>
                <p>${admNo}</p>
            </div>
        </body>
        </html>
    `);
    printWindow.document.close();
}
</script>
<?php
}
?> 