/**
 * Webcam photo capture for patient registration.
 *
 * Markup: a [data-webcam] container holding <video>, <canvas>, a hidden
 * input[name=photo_data], a preview <img>, and buttons with
 * data-webcam-start / data-webcam-capture / data-webcam-cancel.
 * Cameras only work on secure origins (https or localhost); otherwise the
 * button explains that and users fall back to file upload.
 */
export function initWebcam() {
    document.querySelectorAll('[data-webcam]').forEach((root) => {
        const video = root.querySelector('video');
        const canvas = root.querySelector('canvas');
        const output = root.querySelector('input[name=photo_data]');
        const preview = document.querySelector(root.dataset.webcam);
        const startBtn = root.querySelector('[data-webcam-start]');
        const captureBtn = root.querySelector('[data-webcam-capture]');
        const cancelBtn = root.querySelector('[data-webcam-cancel]');
        const status = root.querySelector('[data-webcam-status]');
        let stream = null;

        const stop = () => {
            stream?.getTracks().forEach((t) => t.stop());
            stream = null;
            video.classList.add('d-none');
            captureBtn.classList.add('d-none');
            cancelBtn.classList.add('d-none');
            startBtn.classList.remove('d-none');
        };

        startBtn.addEventListener('click', async () => {
            if (!navigator.mediaDevices?.getUserMedia) {
                status.textContent = 'Camera not available here (requires HTTPS or localhost). Please upload a photo instead.';
                return;
            }
            try {
                stream = await navigator.mediaDevices.getUserMedia({ video: { width: 640, height: 480 }, audio: false });
                video.srcObject = stream;
                video.classList.remove('d-none');
                captureBtn.classList.remove('d-none');
                cancelBtn.classList.remove('d-none');
                startBtn.classList.add('d-none');
                status.textContent = '';
            } catch (err) {
                status.textContent = 'Could not access the camera: ' + err.message;
            }
        });

        captureBtn.addEventListener('click', () => {
            // Square crop from the centre of the frame.
            const size = Math.min(video.videoWidth, video.videoHeight);
            canvas.width = canvas.height = 400;
            canvas.getContext('2d').drawImage(
                video,
                (video.videoWidth - size) / 2, (video.videoHeight - size) / 2, size, size,
                0, 0, 400, 400,
            );
            output.value = canvas.toDataURL('image/jpeg', 0.85);
            if (preview) {
                preview.src = output.value;
                preview.classList.remove('d-none');
            }
            status.textContent = 'Photo captured.';
            stop();
        });

        cancelBtn.addEventListener('click', stop);
    });
}
