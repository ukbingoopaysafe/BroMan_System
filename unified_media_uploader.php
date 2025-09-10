<?php
// Unified Media Uploader Component
// This component handles both images and videos via drag-and-drop

require_once __DIR__ . '/lang/translate.php';
require_once __DIR__ . '/media_functions.php';
?>

<div class="unified-media-uploader">
    <h3><?php echo t('media_upload'); ?></h3>
    
    <!-- Upload Area -->
    <div class="upload-area" id="uploadArea">
        <div class="upload-content">
            <i class="fas fa-cloud-upload-alt upload-icon"></i>
            <p class="upload-text"><?php echo t('drag_drop_files_here'); ?></p>
            <p class="upload-subtext"><?php echo t('supported_formats'); ?>: JPG, PNG, WEBP, MP4, AVI, MOV, WMV, FLV, WEBM (videos up to 250MB)</p>
            <button type="button" class="btn btn-secondary" id="selectFilesBtn">
                <i class="fas fa-folder-open"></i> <?php echo t('select_files'); ?>
            </button>
        </div>
    <input type="file" id="fileInput" multiple accept="image/*,video/*,audio/*" style="display: none;">
    </div>
    
    <!-- File Preview Area -->
    <div class="file-previews" id="filePreviews" style="display: none;">
        <h4><?php echo t('selected_files'); ?></h4>
        <div class="preview-grid" id="previewGrid"></div>
    </div>
    
    <!-- Upload Progress -->
    <div class="upload-progress" id="uploadProgress" style="display: none;">
        <div class="progress-bar">
            <div class="progress-fill" id="progressFill"></div>
        </div>
        <div class="progress-text" id="progressText"><?php echo t('uploading'); ?>...</div>
    </div>
    
    <!-- Upload Results -->
    <div class="upload-results" id="uploadResults" style="display: none;">
        <h4><?php echo t('upload_results'); ?></h4>
        <div id="resultsList"></div>
    </div>
</div>

<style>
.unified-media-uploader {
    margin: 20px 0;
    padding: 20px;
    border: 1px solid #ddd;
    border-radius: 8px;
    background: #f9f9f9;
}

.upload-area {
    border: 2px dashed #ccc;
    border-radius: 8px;
    padding: 40px;
    text-align: center;
    background: white;
    transition: all 0.3s ease;
    cursor: pointer;
}

.upload-area:hover {
    border-color: #007bff;
    background: #f8f9fa;
}

.upload-area.dragover {
    border-color: #007bff;
    background: #e3f2fd;
}

.upload-content {
    /* allow button clicks inside upload content */
    pointer-events: auto;
}

.upload-icon {
    font-size: 48px;
    color: #ccc;
    margin-bottom: 16px;
}

.upload-text {
    font-size: 18px;
    color: #333;
    margin-bottom: 8px;
}

.upload-subtext {
    font-size: 14px;
    color: #666;
    margin-bottom: 20px;
}

.preview-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 15px;
    margin-top: 15px;
}

.file-preview {
    position: relative;
    border: 1px solid #ddd;
    border-radius: 8px;
    overflow: hidden;
    background: white;
}

.file-preview img,
.file-preview video {
    width: 100%;
    height: 150px;
    object-fit: cover;
}

.file-info {
    padding: 10px;
    background: #f8f9fa;
}

.file-name {
    font-weight: bold;
    font-size: 14px;
    margin-bottom: 5px;
    word-break: break-word;
}

.file-size {
    font-size: 12px;
    color: #666;
}

.file-type {
    font-size: 12px;
    color: #007bff;
    font-weight: bold;
}

.remove-file {
    position: absolute;
    top: 5px;
    right: 5px;
    background: #dc3545;
    color: white;
    border: none;
    border-radius: 50%;
    width: 25px;
    height: 25px;
    cursor: pointer;
    font-size: 12px;
}

.upload-progress {
    margin: 20px 0;
}

.progress-bar {
    width: 100%;
    height: 20px;
    background: #e9ecef;
    border-radius: 10px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background: #007bff;
    width: 0%;
    transition: width 0.3s ease;
}

.progress-text {
    text-align: center;
    margin-top: 10px;
    font-weight: bold;
}

.upload-results {
    margin-top: 20px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 8px;
}

.result-item {
    padding: 10px;
    margin: 5px 0;
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.result-item.success {
    background: #d4edda;
    border: 1px solid #c3e6cb;
    color: #155724;
}

.result-item.error {
    background: #f8d7da;
    border: 1px solid #f5c6cb;
    color: #721c24;
}

.result-icon {
    margin-right: 10px;
}

.result-message {
    flex: 1;
}

.result-actions {
    display: flex;
    gap: 10px;
}

.btn {
    padding: 5px 10px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 12px;
}

.btn-primary {
    background: #007bff;
    color: white;
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-danger {
    background: #dc3545;
    color: white;
}

.btn-success {
    background: #28a745;
    color: white;
}
</style>

<script>
class UnifiedMediaUploader {
    constructor(propertyId, csrfToken) {
        this.propertyId = propertyId;
        this.csrfToken = csrfToken;
        this.selectedFiles = [];
        this.uploadedFiles = [];
        
        this.initializeElements();
        this.bindEvents();
    }
    
    initializeElements() {
        this.uploadArea = document.getElementById('uploadArea');
        this.fileInput = document.getElementById('fileInput');
        this.selectFilesBtn = document.getElementById('selectFilesBtn');
        this.filePreviews = document.getElementById('filePreviews');
        this.previewGrid = document.getElementById('previewGrid');
        this.uploadProgress = document.getElementById('uploadProgress');
        this.progressFill = document.getElementById('progressFill');
        this.progressText = document.getElementById('progressText');
        this.uploadResults = document.getElementById('uploadResults');
        this.resultsList = document.getElementById('resultsList');
    }
    
    bindEvents() {
        // Click to select files
        this.selectFilesBtn.addEventListener('click', () => {
            this.fileInput.click();
        });
        
        // File input change
        this.fileInput.addEventListener('change', (e) => {
            this.handleFiles(e.target.files);
        });
        
        // Drag and drop events
        this.uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            this.uploadArea.classList.add('dragover');
        });
        
        this.uploadArea.addEventListener('dragleave', (e) => {
            e.preventDefault();
            this.uploadArea.classList.remove('dragover');
        });
        
        this.uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            this.uploadArea.classList.remove('dragover');
            this.handleFiles(e.dataTransfer.files);
        });
    }
    
    handleFiles(files) {
        Array.from(files).forEach(file => {
            if (this.validateFile(file)) {
                this.selectedFiles.push(file);
            }
        });
        
        this.updatePreview();
        // If property exists, upload immediately.
        // Small delay to avoid racing with server-side session write after page render on some clients.
        if (this.propertyId && this.propertyId > 0) {
            setTimeout(() => this.uploadFiles(), 250);
        }
    }
    
    validateFile(file) {
        const allowedImageTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
        const allowedVideoTypes = ['video/mp4', 'video/avi', 'video/quicktime', 'video/x-ms-wmv', 'video/x-flv', 'video/webm', 'video/ogg', 'video/mpeg'];
        const allowedAudioTypes = ['audio/mpeg', 'audio/mp3', 'audio/wav', 'audio/ogg', 'audio/m4a', 'audio/mp4', 'audio/webm', 'audio/x-m4a'];
        const allowedTypes = [...allowedImageTypes, ...allowedVideoTypes, ...allowedAudioTypes];

        if (!allowedTypes.includes(file.type)) {
            alert(`File ${file.name} has an unsupported format.`);
            return false;
        }

        const maxSize = file.type.startsWith('video/') ? 250 * 1024 * 1024 : (file.type.startsWith('audio/') ? 10 * 1024 * 1024 : 10 * 1024 * 1024); // 250MB for videos, 10MB for audio/images
        
        if (file.size > maxSize) {
            alert(`File ${file.name} is too large. Maximum size is ${this.formatFileSize(maxSize)}.`);
            return false;
        }
        
        return true;
    }
    
    updatePreview() {
        this.previewGrid.innerHTML = '';
        
        this.selectedFiles.forEach((file, index) => {
            const preview = this.createFilePreview(file, index);
            this.previewGrid.appendChild(preview);
        });
        
        this.filePreviews.style.display = this.selectedFiles.length > 0 ? 'block' : 'none';
    }
    
    createFilePreview(file, index) {
        const preview = document.createElement('div');
        preview.className = 'file-preview';
        
        const isVideo = file.type.startsWith('video/');
        const isAudio = file.type.startsWith('audio/');
        let mediaElement;
        if (isVideo) {
            mediaElement = document.createElement('video');
            mediaElement.controls = true;
            mediaElement.src = URL.createObjectURL(file);
        } else if (isAudio) {
            mediaElement = document.createElement('audio');
            mediaElement.controls = true;
            mediaElement.src = URL.createObjectURL(file);
            mediaElement.style.width = '100%';
            mediaElement.style.height = '48px';
            mediaElement.style.objectFit = 'cover';
        } else {
            mediaElement = document.createElement('img');
            mediaElement.src = URL.createObjectURL(file);
        }
        
        const fileInfo = document.createElement('div');
        fileInfo.className = 'file-info';
        fileInfo.innerHTML = `
            <div class="file-name">${file.name}</div>
            <div class="file-size">${this.formatFileSize(file.size)}</div>
            <div class="file-type">${isVideo ? 'Video' : (isAudio ? 'Audio' : 'Image')}</div>
        `;
        
        const removeBtn = document.createElement('button');
        removeBtn.className = 'remove-file';
        removeBtn.innerHTML = '×';
        removeBtn.addEventListener('click', () => {
            this.removeFile(index);
        });
        
        preview.appendChild(mediaElement);
        preview.appendChild(fileInfo);
        preview.appendChild(removeBtn);
        
        return preview;
    }
    
    removeFile(index) {
        this.selectedFiles.splice(index, 1);
        this.updatePreview();
    }
    
    async uploadFiles() {
        if (this.selectedFiles.length === 0) {
            alert('Please select files to upload.');
            return;
        }
        
        this.showProgress();
        this.uploadedFiles = [];
        
        for (let i = 0; i < this.selectedFiles.length; i++) {
            const file = this.selectedFiles[i];
            let type;
            if (file.type.startsWith('video/')) type = 'video';
            else if (file.type.startsWith('audio/')) type = 'audio';
            else type = 'image';
            
            try {
                const result = await this.uploadFile(file, type);
                this.uploadedFiles.push(result);
                this.updateProgress((i + 1) / this.selectedFiles.length * 100);
            } catch (error) {
                console.error('Upload error:', error);
                this.uploadedFiles.push({
                    success: false,
                    message: error.message,
                    fileName: file.name
                });
            }
        }
        
        this.hideProgress();
        this.showResults();
    }
    
    async uploadFile(file, type) {
        const formData = new FormData();
        formData.append('property_id', this.propertyId);
        formData.append('upload_type', type);
        formData.append('csrf_token', this.csrfToken);
        formData.append(type, file);
        
    const response = await fetch('upload_media.php', {
        method: 'POST',
        body: formData,
        // ensure session cookie is sent so server-side CSRF/session checks work
        credentials: 'same-origin'
    });

    const result = await response.json();

    if (!result.success) {
        // prefer server-provided message when available
        throw new Error(result.message || 'Upload failed');
    }
        
        return result;
    }
    
    showProgress() {
        this.uploadProgress.style.display = 'block';
        this.progressFill.style.width = '0%';
        this.progressText.textContent = 'Uploading...';
    }
    
    updateProgress(percentage) {
        this.progressFill.style.width = percentage + '%';
        this.progressText.textContent = `Uploading... ${Math.round(percentage)}%`;
    }
    
    hideProgress() {
        this.uploadProgress.style.display = 'none';
    }
    
    showResults() {
        this.resultsList.innerHTML = '';
        
        this.uploadedFiles.forEach(result => {
            const resultItem = document.createElement('div');
            resultItem.className = `result-item ${result.success ? 'success' : 'error'}`;
            
            const icon = result.success ? '✓' : '✗';
            resultItem.innerHTML = `
                <span class="result-icon">${icon}</span>
                <span class="result-message">${result.fileName || result.message}</span>
                <div class="result-actions">
                    ${result.success ? '<button class="btn btn-success" onclick="viewFile(\'' + (result.file_path || result.temp_path) + '\')">View</button>' : ''}
                </div>
            `;
            
            this.resultsList.appendChild(resultItem);
        });
        
        this.uploadResults.style.display = 'block';
        
        // Clear selected files after successful upload
        if (this.uploadedFiles.some(r => r.success)) {
            this.selectedFiles = [];
            this.updatePreview();
        }
    }
    
    formatFileSize(bytes) {
        if (bytes >= 1073741824) {
            return (bytes / 1073741824).toFixed(2) + ' GB';
        } else if (bytes >= 1048576) {
            return (bytes / 1048576).toFixed(2) + ' MB';
        } else if (bytes >= 1024) {
            return (bytes / 1024).toFixed(2) + ' KB';
        } else {
            return bytes + ' bytes';
        }
    }
}

// Global function to view uploaded files
function viewFile(filePath) {
    window.open(filePath, '_blank');
}

// Initialize uploader when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    const propEl = document.querySelector('[data-property-id]');
    const propertyId = propEl ? parseInt(propEl.dataset.propertyId || '0', 10) : 0;
    const csrfToken = document.querySelector('[data-csrf-token]')?.dataset.csrfToken;

    const container = document.querySelector('.unified-media-uploader');
    if (!container) return;

    // If there's no CSRF token, don't enable uploader (security)
    if (!csrfToken) {
        const note = document.createElement('div');
        note.className = 'upload-note';
        note.style.color = '#856404';
        note.style.background = '#fff3cd';
        note.style.padding = '10px';
        note.style.borderRadius = '6px';
        note.style.marginTop = '10px';
        note.textContent = 'Media uploader is disabled (CSRF token missing).';
        container.appendChild(note);
        return;
    }

    // If propertyId is not a saved property yet, show an instruction and do not enable uploads
    if (!propertyId || propertyId <= 0) {
        const note = document.createElement('div');
        note.className = 'upload-note';
        note.style.color = '#0c5460';
        note.style.background = '#d1ecf1';
        note.style.padding = '10px';
        note.style.borderRadius = '6px';
        note.style.marginTop = '10px';
        note.innerHTML = 'يمكنك الآن رفع الوسائط مؤقتًا؛ سيتم ربطها بالعقار بعد حفظ النموذج.';
        container.appendChild(note);
    }

    // Initialize uploader for an existing property, but wait until server session/CSRF are ready
    function initUploaderWhenReady(retries = 8) {
        if (!propertyId || propertyId <= 0) {
            window.mediaUploader = new UnifiedMediaUploader(propertyId, csrfToken);
            return;
        }

        fetch('session_ready.php', { method: 'GET', credentials: 'same-origin' })
            .then(r => r.json())
            .then(data => {
                if (data && data.ready) {
                    const tokenToUse = data.csrf_token || csrfToken;
                    window.mediaUploader = new UnifiedMediaUploader(propertyId, tokenToUse);
                } else if (retries > 0) {
                    setTimeout(() => initUploaderWhenReady(retries - 1), 250);
                } else {
                    // fallback to avoid UI lock
                    window.mediaUploader = new UnifiedMediaUploader(propertyId, csrfToken);
                }
            }).catch(err => {
                if (retries > 0) setTimeout(() => initUploaderWhenReady(retries - 1), 250);
                else window.mediaUploader = new UnifiedMediaUploader(propertyId, csrfToken);
            });
    }

    initUploaderWhenReady();

    // No separate upload button.
    // If this is an add page (propertyId <= 0), intercept form submission and append queued files
    if (!propertyId || propertyId <= 0) {
        const form = container.closest('form');
        if (form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                const fm = new FormData(form);

                const uploader = window.mediaUploader;
                if (uploader && Array.isArray(uploader.selectedFiles)) {
                    uploader.selectedFiles.forEach(f => {
                        if (f.type.startsWith('image/')) fm.append('images[]', f, f.name);
                        else if (f.type.startsWith('audio/')) fm.append('audio[]', f, f.name);
                        else if (f.type.startsWith('video/')) fm.append('videos[]', f, f.name);
                    });
                }

                fetch(form.action || window.location.href, {
                    method: form.method || 'POST',
                    body: fm,
                    credentials: 'same-origin'
                }).then(response => {
                    if (response.redirected) {
                        window.location = response.url;
                    } else {
                        return response.text().then(html => {
                            document.open(); document.write(html); document.close();
                        });
                    }
                }).catch(err => {
                    alert('حدث خطأ أثناء حفظ النموذج: ' + err.message);
                });
            });
        }
    }
});
</script>