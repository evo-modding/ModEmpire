  </main>

</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // --- Helper for copy-to-clipboard buttons and delete confirmation ---
    document.body.addEventListener('click', function(e) {
        if (e.target.matches('.btn-copy')) {
            const url = e.target.dataset.url;
            navigator.clipboard.writeText(url).then(() => {
                e.target.textContent = 'Copied!';
                setTimeout(() => { e.target.textContent = 'Copy Link'; }, 2000);
            }).catch(err => {
                console.error('Failed to copy text: ', err);
                alert('Failed to copy link.');
            });
        }
        if (e.target.matches('.btn-delete')) {
            if (!confirm('Are you sure you want to delete this file? This action is permanent.')) {
                e.preventDefault();
            }
        }
    });

    // --- NEW: Custom File Input Handler ---
    const fileUpload = document.getElementById('file-upload');
    const fileUploadFilename = document.getElementById('file-upload-filename');
    if (fileUpload && fileUploadFilename) {
        fileUpload.addEventListener('change', function() {
            if (this.files && this.files.length > 0) {
                fileUploadFilename.textContent = this.files[0].name;
            } else {
                fileUploadFilename.textContent = 'No file chosen';
            }
        });
    }

    // --- NEW: Download Page Timer ---
    const countdownElement = document.getElementById('countdown');
    const timerMessage = document.getElementById('timer-message');
    const downloadBtn = document.getElementById('download-button');
    const downloadBtnDisabled = document.getElementById('download-button-disabled');

    if (countdownElement && downloadBtn && downloadBtnDisabled) {
        let seconds = 10; // Set countdown time
        
        const timer = setInterval(function() {
            seconds--;
            if (seconds >= 0) {
                countdownElement.textContent = seconds;
            }
            
            if (seconds < 0) {
                clearInterval(timer);
                if (timerMessage) timerMessage.style.display = 'none';
                downloadBtnDisabled.style.display = 'none';
                downloadBtn.style.display = 'inline-block'; // Show real button
            }
        }, 1000);
    }

});
</script>
</body>
</html>
