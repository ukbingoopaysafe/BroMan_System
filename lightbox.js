// Lightbox functionality for property images
class PropertyLightbox {
    constructor() {
        this.currentImageIndex = 0;
        this.images = [];
        this.lightboxElement = null;
        this.minScale = 0.5;
        this.maxScale = 3;
        this.currentScale = 1;
        this.init();
    }

    init() {
        this.createLightboxHTML();
        this.bindEvents();
        // Prepare default gallery (media) so lightbox sizing/structure is ready
        this.collectImagesForGallery('media');
    }

    createLightboxHTML() {
        const lightboxHTML = `
            <div id="property-lightbox" class="lightbox">
                <div class="lightbox-content">
                    <span class="lightbox-close">&times;</span>
                    <span class="lightbox-nav lightbox-prev">&#10094;</span>
                    <img class="lightbox-image" src="" alt="Property Image">
                    <span class="lightbox-nav lightbox-next">&#10095;</span>
                    <div class="lightbox-counter">
                        <span class="current">1</span> / <span class="total">1</span>
                    </div>
                </div>
            </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', lightboxHTML);
        this.lightboxElement = document.getElementById('property-lightbox');
    }

    bindEvents() {
        // Close lightbox events
        const closeBtn = this.lightboxElement.querySelector('.lightbox-close');
        closeBtn.addEventListener('click', () => this.close());

        // Click outside to close
        this.lightboxElement.addEventListener('click', (e) => {
            if (e.target === this.lightboxElement) {
                this.close();
            }
        });

        // Navigation events
        const prevBtn = this.lightboxElement.querySelector('.lightbox-prev');
        const nextBtn = this.lightboxElement.querySelector('.lightbox-next');
        
        prevBtn.addEventListener('click', () => this.prev());
        nextBtn.addEventListener('click', () => this.next());

        // Keyboard events
        document.addEventListener('keydown', (e) => {
            if (this.lightboxElement.style.display === 'block') {
                switch(e.key) {
                    case 'Escape':
                        this.close();
                        break;
                    case 'ArrowLeft':
                        this.prev();
                        break;
                    case 'ArrowRight':
                        this.next();
                        break;
                }
            }
        });

        // Image click events (support property images and attachment images)
        document.addEventListener('click', (e) => {
            const target = e.target.closest('.property-image-clickable, .attachment-image-clickable');
            if (target) {
                e.preventDefault();
                // determine gallery key
                let gallery = 'media';
                if (target.classList.contains('attachment-image-clickable')) gallery = 'attachments';
                // open gallery and compute index of clicked image
                this.collectImagesForGallery(gallery);
                const src = (target.dataset && target.dataset.src) ? target.dataset.src : target.src;
                const idx = this.images.findIndex(i => i.src === src);
                this.currentImageIndex = idx >= 0 ? idx : 0;
                this.open(this.currentImageIndex);
            }
        });
    }

    // collect images for a specific gallery key ('media' or 'attachments')
    collectImagesForGallery(gallery) {
        let selector = '.property-image-clickable';
        if (gallery === 'attachments') selector = '.attachment-image-clickable';
        const imageElements = document.querySelectorAll(selector);
        this.images = Array.from(imageElements).map(img => ({
            src: img.dataset && img.dataset.src ? img.dataset.src : img.src,
            alt: img.alt || (img.dataset && img.dataset.alt ? img.dataset.alt : 'Image')
        }));
    }

    open(imageIndex = 0) {
        // open assumes this.images has been prepared for the correct gallery
        if (!Array.isArray(this.images) || this.images.length === 0) return;

        this.currentImageIndex = imageIndex;
        // reset zoom
        this.currentScale = 1;
        this.updateImage();
        this.updateCounter();
        this.updateNavigation();

        this.lightboxElement.style.display = 'block';
        document.body.style.overflow = 'hidden'; // Prevent background scrolling

        // attach zoom handlers
        const img = this.lightboxElement.querySelector('.lightbox-image');
        // wheel zoom
        this.wheelHandler = (ev) => {
            ev.preventDefault();
            const delta = -ev.deltaY || ev.wheelDelta || -ev.detail;
            const step = 0.1;
            if (delta > 0) this.currentScale = Math.min(this.maxScale, this.currentScale + step);
            else this.currentScale = Math.max(this.minScale, this.currentScale - step);
            img.style.transform = `scale(${this.currentScale})`;
        };
        img.addEventListener('wheel', this.wheelHandler, { passive: false });
        // double click toggle
        this.dblHandler = () => {
            if (this.currentScale === 1) this.currentScale = 2;
            else this.currentScale = 1;
            img.style.transform = `scale(${this.currentScale})`;
        };
        img.addEventListener('dblclick', this.dblHandler);
    }

    close() {
        this.lightboxElement.style.display = 'none';
        document.body.style.overflow = ''; // Restore scrolling
        // remove zoom handlers if present
        const img = this.lightboxElement.querySelector('.lightbox-image');
        if (img) {
            if (this.wheelHandler) img.removeEventListener('wheel', this.wheelHandler);
            if (this.dblHandler) img.removeEventListener('dblclick', this.dblHandler);
            img.style.transform = '';
        }
    }

    prev() {
        this.currentImageIndex = (this.currentImageIndex - 1 + this.images.length) % this.images.length;
        this.updateImage();
        this.updateCounter();
    }

    next() {
        this.currentImageIndex = (this.currentImageIndex + 1) % this.images.length;
        this.updateImage();
        this.updateCounter();
    }

    updateImage() {
        const img = this.lightboxElement.querySelector('.lightbox-image');
        const currentImage = this.images[this.currentImageIndex];
        img.src = currentImage.src;
        img.alt = currentImage.alt;
        // reset transform on image change
        if (typeof this.currentScale === 'undefined') this.currentScale = 1;
        img.style.transformOrigin = 'center center';
        img.style.transition = 'transform 120ms ease';
        img.style.transform = `scale(${this.currentScale})`;
    }

    updateCounter() {
        const currentSpan = this.lightboxElement.querySelector('.lightbox-counter .current');
        const totalSpan = this.lightboxElement.querySelector('.lightbox-counter .total');
        
        currentSpan.textContent = this.currentImageIndex + 1;
        totalSpan.textContent = this.images.length;
    }

    updateNavigation() {
        const prevBtn = this.lightboxElement.querySelector('.lightbox-prev');
        const nextBtn = this.lightboxElement.querySelector('.lightbox-next');
        const counter = this.lightboxElement.querySelector('.lightbox-counter');
        
        if (this.images.length <= 1) {
            prevBtn.style.display = 'none';
            nextBtn.style.display = 'none';
            counter.style.display = 'none';
        } else {
            prevBtn.style.display = 'block';
            nextBtn.style.display = 'block';
            counter.style.display = 'block';
        }
    }
}

// Media upload functionality
class MediaUploader {
    constructor(propertyId, csrfToken) {
        this.propertyId = propertyId;
        this.csrfToken = csrfToken;
        this.init();
    }

    init() {
        this.bindUploadEvents();
        this.bindDeleteEvents();
    }

    bindUploadEvents() {
        // Image upload
        const imageInput = document.getElementById('image-upload');
        const imageUploadBtn = document.getElementById('image-upload-btn');
        
        if (imageUploadBtn) {
            imageUploadBtn.addEventListener('click', () => imageInput.click());
        }
        
        if (imageInput) {
            imageInput.addEventListener('change', (e) => {
                if (e.target.files.length > 0) {
                    this.uploadFiles(e.target.files, 'image');
                }
            });
        }

        // Audio upload
        const audioInput = document.getElementById('audio-upload');
        const audioUploadBtn = document.getElementById('audio-upload-btn');
        
        if (audioUploadBtn) {
            audioUploadBtn.addEventListener('click', () => audioInput.click());
        }
        
        if (audioInput) {
            audioInput.addEventListener('change', (e) => {
                if (e.target.files.length > 0) {
                    this.uploadFiles(e.target.files, 'audio');
                }
            });
        }

        // Drag and drop for images
        const imageUploadArea = document.getElementById('image-upload-area');
        if (imageUploadArea) {
            this.bindDragAndDrop(imageUploadArea, 'image');
        }

        // Drag and drop for audio
        const audioUploadArea = document.getElementById('audio-upload-area');
        if (audioUploadArea) {
            this.bindDragAndDrop(audioUploadArea, 'audio');
        }
    }

    bindDragAndDrop(element, type) {
        element.addEventListener('dragover', (e) => {
            e.preventDefault();
            element.classList.add('dragover');
        });

        element.addEventListener('dragleave', () => {
            element.classList.remove('dragover');
        });

        element.addEventListener('drop', (e) => {
            e.preventDefault();
            element.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                this.uploadFiles(files, type);
            }
        });
    }

    bindDeleteEvents() {
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('delete-image-btn')) {
                const imageId = e.target.dataset.imageId;
                this.deleteMedia('image', imageId, e.target);
            }
            
            if (e.target.classList.contains('delete-audio-btn')) {
                const audioId = e.target.dataset.audioId;
                this.deleteMedia('audio', audioId, e.target);
            }
        });
    }

    uploadFiles(files, type) {
        Array.from(files).forEach(file => {
            this.uploadFile(file, type);
        });
    }

    uploadFile(file, type) {
        const formData = new FormData();
        formData.append(type, file);
        formData.append('property_id', this.propertyId);
        formData.append('upload_type', type);
        formData.append('csrf_token', this.csrfToken);

        // Create progress indicator
        const progressContainer = this.createProgressIndicator(file.name);
        
        fetch('upload_media.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            progressContainer.remove();
            
            if (data.success) {
                this.showMessage('success', data.message);
                // Visual feedback: highlight the newly uploaded media
                if (type === 'image') {
                    this.highlightLastImage();
                }
                // Optionally add audio item feedback if needed
                // Update the media display gracefully
                this.refreshMediaDisplay(type, data);
            } else {
                this.showMessage('error', data.message);
            }
        })
        .catch(error => {
            progressContainer.remove();
            this.showMessage('error', 'Upload failed: ' + error.message);
        });
    }

    deleteMedia(type, mediaId, buttonElement) {
        if (!confirm('Are you sure you want to delete this ' + type + '?')) {
            return;
        }

        const formData = new FormData();
        formData.append('media_type', type);
        formData.append('media_id', mediaId);
        formData.append('csrf_token', this.csrfToken);

        fetch('delete_media.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.showMessage('success', data.message);
                // Remove the media item from DOM
                const mediaItem = buttonElement.closest('.image-item, .audio-item');
                if (mediaItem) {
                    mediaItem.remove();
                }
            } else {
                this.showMessage('error', data.message);
            }
        })
        .catch(error => {
            this.showMessage('error', 'Delete failed: ' + error.message);
        });
    }

    createProgressIndicator(filename) {
        const progressHTML = `
            <div class="upload-progress-item">
                <div>Uploading: ${filename}</div>
                <div class="upload-progress">
                    <div class="upload-progress-bar"></div>
                </div>
            </div>
        `;
        
        const container = document.createElement('div');
        container.innerHTML = progressHTML;
        
        const uploadArea = document.querySelector('.upload-area');
        uploadArea.appendChild(container);
        
        return container;
    }

    refreshMediaDisplay(type, data) {
        // Enhanced refresh with better user feedback
        // First show immediate visual feedback, then reload to ensure data consistency
        if (type === 'image') {
            this.highlightLastImage();
        }
        
        // Provide user feedback about the save action
        this.showMessage('success', `${type === 'image' ? 'Image' : 'Audio'} uploaded successfully!`);
        
        // Reload after a short delay to ensure the new media is displayed
        setTimeout(() => {
            location.reload();
        }, 1500); // Increased delay to allow users to see the feedback
    }

    highlightLastImage() {
        // Add a temporary visual indicator to the newest image (assumes appended/rendered soon)
        const images = document.querySelectorAll('.images-grid .image-item img');
        if (images.length > 0) {
            const lastImg = images[images.length - 1];
            lastImg.style.outline = '3px solid #28a745';
            lastImg.style.outlineOffset = '2px';
            const overlay = document.createElement('div');
            overlay.textContent = 'Saved!';
            overlay.style.position = 'absolute';
            overlay.style.top = '8px';
            overlay.style.right = '8px';
            overlay.style.background = 'rgba(40,167,69,0.9)';
            overlay.style.color = '#fff';
            overlay.style.padding = '4px 8px';
            overlay.style.borderRadius = '4px';
            overlay.style.fontSize = '12px';
            const wrapper = lastImg.closest('.image-item');
            if (wrapper) {
                wrapper.style.position = 'relative';
                wrapper.appendChild(overlay);
                setTimeout(() => {
                    lastImg.style.outline = '';
                    overlay.remove();
                }, 2500);
            }
        }
    }

    showMessage(type, message) {
        const messageHTML = `
            <div class="alert alert-${type === 'success' ? 'success' : 'danger'}" style="margin: 10px 0;">
                ${message}
            </div>
        `;
        
        const container = document.querySelector('.media-messages') || document.body;
        container.insertAdjacentHTML('afterbegin', messageHTML);
        
        // Auto-remove message after 5 seconds
        setTimeout(() => {
            const alertElement = container.querySelector('.alert');
            if (alertElement) {
                alertElement.remove();
            }
        }, 5000);
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Initialize lightbox
    new PropertyLightbox();
    
    // Initialize media uploader if on edit page
    const propertyIdElement = document.querySelector('[data-property-id]');
    const csrfTokenElement = document.querySelector('[data-csrf-token]');
    
    if (propertyIdElement && csrfTokenElement) {
        const propertyId = propertyIdElement.dataset.propertyId;
        const csrfToken = csrfTokenElement.dataset.csrfToken;
        new MediaUploader(propertyId, csrfToken);
    }
});

