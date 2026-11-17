document.addEventListener('DOMContentLoaded', function () {
    var startButton = document.getElementById('start-certificate-scan');
    var video = document.getElementById('certificate-camera');
    var placeholder = document.getElementById('certificate-camera-placeholder');
    var status = document.getElementById('certificate-scanner-status');
    var form = document.querySelector('.certificate-code-form');
    var codeInput = document.getElementById('certificate-code');
    var stream = null;
    var scanning = false;

    if (!startButton || !video || !form || !codeInput || !status) {
        return;
    }

    function stopCamera() {
        scanning = false;
        if (stream) {
            stream.getTracks().forEach(function (track) {
                track.stop();
            });
            stream = null;
        }
        video.srcObject = null;
    }

    function acceptScan(rawValue) {
        var certificateCode = rawValue;
        try {
            certificateCode = new URL(rawValue, window.location.href).searchParams.get('code') || rawValue;
        } catch (error) {
            certificateCode = rawValue;
        }

        if (!/^MC-[A-Za-z0-9]{4,64}$/.test(certificateCode)) {
            status.textContent = 'The scanned QR code is not a SHRCMS medical certificate.';
            return false;
        }

        codeInput.value = certificateCode;
        stopCamera();
        form.requestSubmit();
        return true;
    }

    startButton.addEventListener('click', async function () {
        if (!('BarcodeDetector' in window)) {
            status.textContent = 'QR scanning is not supported in this browser. Enter the printed certificate number instead.';
            return;
        }
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            status.textContent = 'Camera access is unavailable. Use HTTPS or localhost, or enter the certificate number manually.';
            return;
        }

        startButton.disabled = true;
        status.textContent = 'Requesting camera access...';

        try {
            var detector = new BarcodeDetector({ formats: ['qr_code'] });
            stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: { ideal: 'environment' } },
                audio: false
            });
            video.srcObject = stream;
            await video.play();
            placeholder.hidden = true;
            video.hidden = false;
            scanning = true;
            status.textContent = 'Point the camera at the certificate QR code.';

            var scanFrame = async function () {
                if (!scanning) {
                    return;
                }
                if (video.readyState >= HTMLMediaElement.HAVE_CURRENT_DATA) {
                    try {
                        var results = await detector.detect(video);
                        if (results.length && acceptScan(results[0].rawValue)) {
                            return;
                        }
                    } catch (error) {
                        status.textContent = 'Could not read the QR code. Hold it steady or enter the code manually.';
                    }
                }
                window.requestAnimationFrame(scanFrame);
            };
            window.requestAnimationFrame(scanFrame);
        } catch (error) {
            stopCamera();
            startButton.disabled = false;
            status.textContent = error.name === 'NotAllowedError'
                ? 'Camera permission was denied. Allow camera access or enter the certificate number manually.'
                : 'Could not start the camera. Use HTTPS or localhost, or enter the code manually.';
        }
    });

    form.addEventListener('submit', stopCamera);
    window.addEventListener('pagehide', stopCamera);
});
