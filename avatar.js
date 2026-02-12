// Avatar Modal Functions
function showAvatarModal() {
    document.getElementById('avatarModal').style.display = 'flex';
}

function closeAvatarModal() {
    document.getElementById('avatarModal').style.display = 'none';
    document.getElementById('avatarFile').value = '';
    document.getElementById('uploadPreview').style.display = 'none';
}

// Avatar Upload Functionality
document.addEventListener('DOMContentLoaded', function() {
    // Avatar click event
    const playerAvatar = document.getElementById('playerAvatar');
    if (playerAvatar) {
        playerAvatar.addEventListener('click', showAvatarModal);
    }
    
    // Modal close events
    const closeModal = document.getElementById('closeModal');
    if (closeModal) {
        closeModal.addEventListener('click', closeAvatarModal);
    }
    
    const uploadArea = document.getElementById('uploadArea');
    const avatarFile = document.getElementById('avatarFile');
    const uploadPreview = document.getElementById('uploadPreview');
    const previewImage = document.getElementById('previewImage');
    const avatarForm = document.getElementById('avatarForm');
    const selectFileBtn = document.getElementById('selectFileBtn');
    const confirmUploadBtn = document.getElementById('confirmUploadBtn');
    
    // Click to upload area
    uploadArea.addEventListener('click', function() {
        avatarFile.click();
    });
    
    selectFileBtn.addEventListener('click', function(e) {
        e.stopPropagation(); // Prevent bubbling to upload area
        avatarFile.click();
    });
    
    // File preview
    avatarFile.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            // Check file size (2MB limit)
            if (file.size > 2 * 1024 * 1024) {
                alert('File size must be less than 2MB');
                avatarFile.value = ''; // Clear the file
                return;
            }
            
            // Check file type
            if (!file.type.match('image.*')) {
                alert('Please select an image file');
                avatarFile.value = ''; // Clear the file
                return;
            }
            
            // Show preview
            const reader = new FileReader();
            reader.onload = function(e) {
                previewImage.src = e.target.result;
                uploadPreview.style.display = 'block';
                confirmUploadBtn.style.display = 'block';
            };
            reader.readAsDataURL(file);
        } else {
            // Hide preview if no file selected
            uploadPreview.style.display = 'none';
            confirmUploadBtn.style.display = 'none';
        }
    });
    
    // Form submission (triggered by confirm button)
    confirmUploadBtn.addEventListener('click', function(e) {
        e.preventDefault();
        
        // Check if file is selected
        const file = avatarFile.files[0];
        if (!file) {
            alert('Please select a file to upload');
            return;
        }
        
        const formData = new FormData(avatarForm);
        confirmUploadBtn.disabled = true;
        confirmUploadBtn.textContent = 'Uploading...';
        
        fetch('upload_avatar.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('HTTP error! status: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                alert('Avatar uploaded successfully!');
                location.reload(); // Refresh to show new avatar
            } else {
                // Handle both 'error' and 'message' fields for compatibility
                const errorMessage = data.error || data.message || 'Unknown error occurred';
                alert('Error: ' + errorMessage);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Upload failed. Please try again.');
        })
        .finally(() => {
            confirmUploadBtn.disabled = false;
            confirmUploadBtn.textContent = 'Confirm Upload';
        });
    });
    
    // Close modal when clicking outside
    window.addEventListener('click', function(e) {
        const modal = document.getElementById('avatarModal');
        if (e.target === modal) {
            closeAvatarModal();
        }
    });
});