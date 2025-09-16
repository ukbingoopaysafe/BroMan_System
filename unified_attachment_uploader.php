<?php
// Unified Attachment Uploader Component
// Adapted from unified_media_uploader.php to handle generic attachments (images, pdf, docs, spreadsheets, txt)

require_once __DIR__ . '/lang/translate.php';
require_once __DIR__ . '/media_functions.php';
?>

<div class="unified-attachment-uploader">
    <h3><?php echo t('manage_attachments'); ?></h3>
    
    <!-- Upload Area -->
    <div class="upload-area" id="attachmentUploadArea">
        <div class="upload-content">
            <i class="fas fa-paperclip upload-icon"></i>
            <p class="upload-text"><?php echo t('upload_attachment'); ?></p>
            <p class="upload-subtext"><?php echo t('supported_formats'); ?>: JPG, PNG, WEBP, PDF, DOC, DOCX, XLS, XLSX, TXT</p>
            <button type="button" class="btn btn-secondary" id="selectAttachmentFilesBtn">
                <i class="fas fa-folder-open"></i> <?php echo t('select_files'); ?>
            </button>
        </div>
    <input type="file" id="attachmentFileInput" name="attachments[]" multiple accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt" style="display: none;">
    </div>
    
    <!-- File Preview Area -->
    <div class="file-previews" id="attachmentFilePreviews" style="display: none;">
        <h4><?php echo t('selected_files'); ?></h4>
        <div class="preview-grid" id="attachmentPreviewGrid"></div>
    </div>
    
    <!-- Upload Progress -->
    <div class="upload-progress" id="attachmentUploadProgress" style="display: none;">
        <div class="progress-bar">
            <div class="progress-fill" id="attachmentProgressFill"></div>
        </div>
        <div class="progress-text" id="attachmentProgressText"><?php echo t('uploading'); ?>...</div>
    </div>
    
    <!-- Upload Results -->
    <div class="upload-results" id="attachmentUploadResults" style="display: none;">
        <h4><?php echo t('upload_results'); ?></h4>
        <div id="attachmentResultsList"></div>
    </div>
</div>

<style>
/* Reuse styles from unified_media_uploader but with smaller previews for attachments */
.unified-attachment-uploader { margin: 20px 0; padding: 20px; border: 1px solid #ddd; border-radius: 8px; background: #fafafa; }
.upload-area { border: 2px dashed #ccc; border-radius: 8px; padding: 30px; text-align: center; background: white; cursor: pointer; }
.upload-area.dragover { border-color: #007bff; background: #eef7ff; }
.preview-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 12px; margin-top: 12px; }
.file-preview { position: relative; border: 1px solid #eee; border-radius: 6px; overflow: hidden; background: white; }
.file-preview img { width: 100%; height: 100px; object-fit: cover; }
.file-info { padding: 8px; font-size: 13px; }
.file-name { font-weight: 600; overflow: hidden; white-space: nowrap; text-overflow: ellipsis; display: block; }
.file-type { color: #666; font-size: 12px; }
.remove-file { position: absolute; top: 6px; right: 6px; background: #dc3545; color: white; border: none; border-radius: 50%; width: 26px; height: 26px; cursor: pointer; }
</style>

<script>
class UnifiedAttachmentUploader {
    constructor(propertyId, csrfToken) {
        this.propertyId = propertyId;
        this.csrfToken = csrfToken;
        this.selectedFiles = [];
        this.init();
    }

    init() {
        this.uploadArea = document.getElementById('attachmentUploadArea');
        this.fileInput = document.getElementById('attachmentFileInput');
        this.selectBtn = document.getElementById('selectAttachmentFilesBtn');
        this.previewGrid = document.getElementById('attachmentPreviewGrid');
        this.filePreviews = document.getElementById('attachmentFilePreviews');

        this.selectBtn.addEventListener('click', () => this.fileInput.click());
    this.fileInput.addEventListener('change', (e) => this.handleFiles(e.target.files));

        this.uploadArea.addEventListener('dragover', (e) => { e.preventDefault(); this.uploadArea.classList.add('dragover'); });
        this.uploadArea.addEventListener('dragleave', (e) => { e.preventDefault(); this.uploadArea.classList.remove('dragover'); });
        this.uploadArea.addEventListener('drop', (e) => { e.preventDefault(); this.uploadArea.classList.remove('dragover'); this.handleFiles(e.dataTransfer.files); });
    }

    handleFiles(files) {
        Array.from(files).forEach(f => {
            if (this.validateFile(f)) this.selectedFiles.push(f);
        });
        // If this form is used on the add pages (propertyId = 0), we must ensure the actual file input
        // contains the selected files so they get submitted with the form POST. Use DataTransfer to sync.
        if (!this.propertyId || this.propertyId === 0) {
            try {
                const dt = new DataTransfer();
                this.selectedFiles.forEach(f => dt.items.add(f));
                this.fileInput.files = dt.files;
            } catch (e) {
                // Older browsers may not support DataTransfer constructor; fall back to leaving the input unchanged.
                console.warn('Could not sync files to input:', e);
            }
        }
        this.updatePreview();
        if (this.propertyId && this.propertyId > 0) {
            this.uploadAll();
        }
    }

    validateFile(file) {
        const allowedExt = ['jpg','jpeg','png','webp','pdf','doc','docx','xls','xlsx','txt','csv','odt'];
        const ext = file.name.split('.').pop().toLowerCase();
        if (!allowedExt.includes(ext)) { alert('File type not allowed: ' + file.name); return false; }
        const max = 10 * 1024 * 1024;
        if (file.size > max) { alert('File too large: ' + file.name); return false; }
        return true;
    }

    updatePreview() {
        this.previewGrid.innerHTML = '';
        this.selectedFiles.forEach((file, idx) => {
            const el = document.createElement('div'); el.className = 'file-preview';
            const ext = file.name.split('.').pop().toLowerCase();
            if (['jpg','jpeg','png','webp'].includes(ext)) {
                const img = document.createElement('img'); img.src = URL.createObjectURL(file); el.appendChild(img);
            } else {
                const icon = document.createElement('div'); icon.style.padding = '20px'; icon.innerHTML = '<i class="fas fa-file fa-3x"></i>'; el.appendChild(icon);
            }
            const info = document.createElement('div'); info.className = 'file-info'; info.innerHTML = '<span class="file-name">' + file.name + '</span><span class="file-type">' + this.formatBytes(file.size) + '</span>';
            const btn = document.createElement('button'); btn.className = 'remove-file'; btn.innerText = '×'; btn.addEventListener('click', () => { this.selectedFiles.splice(idx,1); this.updatePreview();
                // Sync the file input when files are removed (for create form)
                if (!this.propertyId || this.propertyId === 0) {
                    try {
                        const dt2 = new DataTransfer();
                        this.selectedFiles.forEach(f => dt2.items.add(f));
                        this.fileInput.files = dt2.files;
                    } catch (e) { console.warn('Could not sync input after remove:', e); }
                }
            });
            el.appendChild(info); el.appendChild(btn);
            this.previewGrid.appendChild(el);
        });
        this.filePreviews.style.display = this.selectedFiles.length ? 'block' : 'none';
    }

    formatBytes(bytes, decimals = 2) {
        if (bytes === 0) return '0 B';
        const k = 1024; const dm = decimals < 0 ? 0 : decimals; const sizes = ['B','KB','MB','GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
    }

    uploadAll() {
        this.selectedFiles.forEach(file => this.uploadFile(file));
    }

    uploadFile(file) {
        const fd = new FormData();
        fd.append('attachments[]', file);
        fd.append('property_id', this.propertyId);
        fd.append('csrf_token', this.csrfToken);

        fetch('upload_attachment.php', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(r => r.json())
        .then(data => {
            if (data && data.success) {
                // Reload to show new attachments (could also append dynamically)
                location.reload();
            } else {
                alert((data && data.message) ? data.message : 'Upload failed');
            }
        }).catch(e => { alert('Upload failed'); });
    }
}

// Auto-initialize if there is a data-property-id on the form (edit page provides real id)
document.addEventListener('DOMContentLoaded', function(){
    // Prefer a form with data-property-id, but fall back to any container with data-property-id
    let pid = 0;
    let csrf = '';
    const form = document.querySelector('form[data-property-id]');
    if (form) {
        pid = parseInt(form.getAttribute('data-property-id') || 0, 10);
        csrf = form.getAttribute('data-csrf-token') || '';
    } else {
        const container = document.querySelector('[data-property-id]');
        if (container) {
            pid = parseInt(container.getAttribute('data-property-id') || 0, 10);
            csrf = container.getAttribute('data-csrf-token') || '';
        }
    }
    // If neither found, do not initialize. Ensure pid is a valid number (not NaN).
    if (!Number.isNaN(pid)) {
        new UnifiedAttachmentUploader(pid, csrf);
    }
});
</script>
