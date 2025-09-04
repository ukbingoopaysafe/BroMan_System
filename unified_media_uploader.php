<?php
// Unified Media Uploader Component
// This component provides a single drag-and-drop interface for both images and videos
?>

<div class="form-section">
    <h3>📁 <?php echo t('media_upload'); ?></h3>
    
    <!-- Unified Media Upload Area -->
    <div id="media-upload-area" class="upload-area">
        <div class="upload-icon">📁</div>
        <p class="upload-text"><?php echo t('drag_drop_media'); ?></p>
        <p class="upload-subtext"><?php echo t('or_click_to_select'); ?></p>
        <button type="button" id="media-upload-btn" class="upload-btn"><?php echo t('choose_media_files'); ?></button>
        <input type="file" id="media-upload" name="media[]" accept="image/jpeg,image/jpg,image/png,image/webp,video/mp4,video/avi,video/mov,video/wmv,video/webm,video/ogg" multiple style="display: none;">
        <div class="upload-info">
            <p class="supported-formats">
                <strong><?php echo t('supported_formats'); ?>:</strong><br>
                <span class="image-formats"><?php echo t('images'); ?>: JPG, JPEG, PNG, WEBP (<?php echo t('max_size'); ?>: 10MB)</span><br>
                <span class="video-formats"><?php echo t('videos'); ?>: MP4, AVI, MOV, WMV, WEBM, OGG (<?php echo t('max_size'); ?>: 50MB)</span>
            </p>
        </div>
    </div>

    <!-- Selected Media Preview -->
    <div id="media-preview" class="media-grid" style="display: none;">
        <div class="media-preview-header">
            <h4><?php echo t('selected_media'); ?></h4>
            <button type="button" id="clear-all-media" class="btn btn-sm btn-danger"><?php echo t('clear_all'); ?></button>
        </div>
        <div id="media-thumbnails" class="media-thumbnails"></div>
    </div>
</div>

<style>
.upload-area {
    border: 2px dashed #ddd;
    border-radius: 8px;
    padding: 40px 20px;
    text-align: center;
    background-color: #fafafa;
    transition: all 0.3s ease;
    cursor: pointer;
    margin-bottom: 20px;
}

.upload-area:hover {
    border-color: #007bff;
    background-color: #f0f8ff;
}

.upload-area.dragover {
    border-color: #007bff;
    background-color: #e3f2fd;
    transform: scale(1.02);
}

.upload-icon {
    font-size: 48px;
    margin-bottom: 15px;
    opacity: 0.7;
}

.upload-text {
    font-size: 18px;
    font-weight: 600;
    color: #333;
    margin-bottom: 5px;
}

.upload-subtext {
    font-size: 14px;
    color: #666;
    margin-bottom: 20px;
}

.upload-btn {
    background-color: #007bff;
    color: white;
    border: none;
    padding: 12px 24px;
    border-radius: 6px;
    font-size: 16px;
    cursor: pointer;
    transition: background-color 0.3s ease;
}

.upload-btn:hover {
    background-color: #0056b3;
}

.upload-info {
    margin-top: 20px;
    padding: 15px;
    background-color: #f8f9fa;
    border-radius: 6px;
    border-left: 4px solid #007bff;
}

.supported-formats {
    font-size: 13px;
    color: #666;
    margin: 0;
    line-height: 1.5;
}

.image-formats {
    color: #28a745;
    font-weight: 500;
}

.video-formats {
    color: #dc3545;
    font-weight: 500;
}

.media-grid {
    margin-top: 20px;
}

.media-preview-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}

.media-preview-header h4 {
    margin: 0;
    color: #333;
}

.media-thumbnails {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 15px;
}

.media-item {
    position: relative;
    border: 1px solid #ddd;
    border-radius: 8px;
    overflow: hidden;
    background-color: white;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    transition: transform 0.2s ease;
}

.media-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}

.media-thumbnail {
    width: 100%;
    height: 150px;
    object-fit: cover;
    background-color: #f8f9fa;
}

.media-info {
    padding: 10px;
    background-color: white;
}

.media-name {
    font-size: 12px;
    font-weight: 600;
    color: #333;
    margin-bottom: 5px;
    word-break: break-all;
}

.media-details {
    font-size: 11px;
    color: #666;
    display: flex;
    justify-content: space-between;
}

.media-type {
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
}

.media-type.image {
    background-color: #d4edda;
    color: #155724;
}

.media-type.video {
    background-color: #f8d7da;
    color: #721c24;
}

.remove-media {
    position: absolute;
    top: 5px;
    right: 5px;
    background-color: rgba(220, 53, 69, 0.9);
    color: white;
    border: none;
    border-radius: 50%;
    width: 24px;
    height: 24px;
    font-size: 12px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background-color 0.2s ease;
}

.remove-media:hover {
    background-color: #dc3545;
}

.video-icon {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-size: 32px;
    color: #dc3545;
    background-color: rgba(255, 255, 255, 0.9);
    border-radius: 50%;
    width: 60px;
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* RTL Support */
[dir="rtl"] .media-preview-header {
    flex-direction: row-reverse;
}

[dir="rtl"] .remove-media {
    right: auto;
    left: 5px;
}

[dir="rtl"] .media-details {
    flex-direction: row-reverse;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const mediaUploadArea = document.getElementById('media-upload-area');
    const mediaUploadBtn = document.getElementById('media-upload-btn');
    const mediaUploadInput = document.getElementById('media-upload');
    const mediaPreview = document.getElementById('media-preview');
    const mediaThumbnails = document.getElementById('media-thumbnails');
    const clearAllBtn = document.getElementById('clear-all-media');
    
    let selectedMedia = [];
    
    // Upload button click
    mediaUploadBtn.addEventListener('click', function() {
        mediaUploadInput.click();
    });
    
    // Upload area click
    mediaUploadArea.addEventListener('click', function() {
        mediaUploadInput.click();
    });
    
    // File selection
    mediaUploadInput.addEventListener('change', function(e) {
        handleMediaFiles(e.target.files);
    });
    
    // Drag and drop
    mediaUploadArea.addEventListener('dragover', function(e) {
        e.preventDefault();
        mediaUploadArea.classList.add('dragover');
    });
    
    mediaUploadArea.addEventListener('dragleave', function(e) {
        e.preventDefault();
        mediaUploadArea.classList.remove('dragover');
    });
    
    mediaUploadArea.addEventListener('drop', function(e) {
        e.preventDefault();
        mediaUploadArea.classList.remove('dragover');
        handleMediaFiles(e.dataTransfer.files);
    });
    
    // Clear all media
    clearAllBtn.addEventListener('click', function() {
        selectedMedia = [];
        updateMediaPreview();
        updateMediaInput();
    });
    
    function handleMediaFiles(files) {
        const imageTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
        const videoTypes = ['video/mp4', 'video/avi', 'video/mov', 'video/wmv', 'video/webm', 'video/ogg'];
        const allowedTypes = [...imageTypes, ...videoTypes];
        const maxImageSize = 10 * 1024 * 1024; // 10MB
        const maxVideoSize = 50 * 1024 * 1024; // 50MB
        
        Array.from(files).forEach(file => {
            if (!allowedTypes.includes(file.type)) {
                alert('Invalid file type: ' + file.name + '. Please select supported image or video files.');
                return;
            }
            
            const maxSize = imageTypes.includes(file.type) ? maxImageSize : maxVideoSize;
            if (file.size > maxSize) {
                const maxSizeMB = maxSize / (1024 * 1024);
                alert('File too large: ' + file.name + '. Maximum size is ' + maxSizeMB + 'MB.');
                return;
            }
            
            selectedMedia.push(file);
        });
        
        updateMediaPreview();
        updateMediaInput();
    }
    
    function updateMediaPreview() {
        mediaThumbnails.innerHTML = '';
        
        if (selectedMedia.length > 0) {
            mediaPreview.style.display = 'block';
            
            selectedMedia.forEach((file, index) => {
                const mediaItem = document.createElement('div');
                mediaItem.className = 'media-item';
                
                const isImage = file.type.startsWith('image/');
                const isVideo = file.type.startsWith('video/');
                
                let thumbnailHTML = '';
                if (isImage) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const img = mediaItem.querySelector('.media-thumbnail');
                        if (img) img.src = e.target.result;
                    };
                    reader.readAsDataURL(file);
                    thumbnailHTML = '<img class="media-thumbnail" src="" alt="' + file.name + '">';
                } else if (isVideo) {
                    thumbnailHTML = '<div class="media-thumbnail"><div class="video-icon">▶</div></div>';
                }
                
                mediaItem.innerHTML = `
                    ${thumbnailHTML}
                    <button type="button" class="remove-media" onclick="removeMedia(${index})" title="Remove">×</button>
                    <div class="media-info">
                        <div class="media-name">${file.name}</div>
                        <div class="media-details">
                            <span class="media-type ${isImage ? 'image' : 'video'}">${isImage ? 'Image' : 'Video'}</span>
                            <span>${formatFileSize(file.size)}</span>
                        </div>
                    </div>
                `;
                
                mediaThumbnails.appendChild(mediaItem);
            });
        } else {
            mediaPreview.style.display = 'none';
        }
    }
    
    function updateMediaInput() {
        // Create a new DataTransfer object to hold the selected files
        const dataTransfer = new DataTransfer();
        selectedMedia.forEach(file => {
            dataTransfer.items.add(file);
        });
        mediaUploadInput.files = dataTransfer.files;
    }
    
    function removeMedia(index) {
        selectedMedia.splice(index, 1);
        updateMediaPreview();
        updateMediaInput();
    }
    
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
    
    // Make removeMedia function globally accessible
    window.removeMedia = removeMedia;
});
</script>