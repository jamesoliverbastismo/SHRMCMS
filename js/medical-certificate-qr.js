document.addEventListener('DOMContentLoaded', function () {
    var container = document.getElementById('medical-certificate-qr');
    if (!container || typeof qrcode !== 'function') {
        return;
    }

    try {
        var qr = qrcode(0, 'M');
        qr.addData(container.getAttribute('data-qr-url'));
        qr.make();
        var image = document.createElement('img');
        image.src = qr.createDataURL(5, 4);
        image.alt = 'Scan to verify this medical certificate';
        container.replaceChildren(image);
    } catch (error) {
        container.textContent = 'QR code could not be generated. Use the certificate number to verify.';
        console.error('Medical certificate QR generation failed.', error);
    }
});
