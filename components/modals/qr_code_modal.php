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
    
    // Use school config if available
    const schoolName = window.SCHOOL_CONFIG?.name || 'Kingsway Preparatory School';
    const schoolMotto = window.SCHOOL_CONFIG?.motto || 'In God We Soar';
    
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
                    font-family: Arial, sans-serif;
                }
                .header {
                    text-align: center;
                    margin-bottom: 20px;
                }
                .header h2 {
                    margin: 0;
                    font-size: 18px;
                }
                .header p {
                    margin: 5px 0 0 0;
                    font-size: 12px;
                    font-style: italic;
                    color: #666;
                }
                img { max-width: 300px; }
                .details { text-align: center; margin-top: 20px; }
                .details h3 { margin: 0 0 5px 0; font-size: 16px; }
                .details p { margin: 0; font-size: 14px; }
            </style>
        </head>
        <body>
            <div class="header">
                <h2>${schoolName}</h2>
                <p>${schoolMotto}</p>
            </div>
            <img src="${qrCode}" onload="window.opener?.PrintManager?.printElement?.('qrCodeModal'); window.close();">
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