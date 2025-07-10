// Enhanced sorting functionality for property tables
document.addEventListener('DOMContentLoaded', function() {
    // Add sorting functionality to all sortable headers
    const sortableHeaders = document.querySelectorAll('.sortable');
    
    sortableHeaders.forEach(header => {
        // Add accessibility attributes
        header.setAttribute('role', 'button');
        header.setAttribute('tabindex', '0');
        
        // Add keyboard support
        header.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                header.click();
            }
        });
        
        // Add visual feedback on click
        header.addEventListener('click', function() {
            // Add loading state
            header.classList.add('sorting-loading');
            
            // Remove loading state after navigation (fallback)
            setTimeout(() => {
                header.classList.remove('sorting-loading');
            }, 1000);
        });
        
        // Add sort hints
        const sortIcon = header.querySelector('.sort-icon');
        if (sortIcon) {
            if (sortIcon.classList.contains('fa-sort')) {
                header.setAttribute('data-sort-hint', 'Click to sort ascending');
            } else if (sortIcon.classList.contains('fa-sort-up')) {
                header.setAttribute('data-sort-hint', 'Click to sort descending');
            } else if (sortIcon.classList.contains('fa-sort-down')) {
                header.setAttribute('data-sort-hint', 'Click to sort ascending');
            }
        }
    });
    
    // Add visual indicators for current sort
    const currentSortColumn = getUrlParameter('sort');
    const currentSortDirection = getUrlParameter('direction');
    
    if (currentSortColumn) {
        sortableHeaders.forEach(header => {
            const onclick = header.getAttribute('onclick');
            if (onclick && onclick.includes(`sort=${currentSortColumn}`)) {
                if (currentSortDirection === 'asc') {
                    header.classList.add('sort-asc');
                } else {
                    header.classList.add('sort-desc');
                }
                header.setAttribute('aria-pressed', 'true');
            }
        });
    }
    
    // Enhanced table responsiveness for sorting
    const table = document.querySelector('.properties-table');
    if (table) {
        // Add horizontal scroll indicator for mobile
        const tableContainer = table.closest('.table-responsive');
        if (tableContainer) {
            const scrollIndicator = document.createElement('div');
            scrollIndicator.className = 'scroll-indicator';
            scrollIndicator.innerHTML = '<i class="fas fa-arrows-alt-h"></i> Scroll horizontally to see more columns';
            
            // Show indicator on mobile if table is wider than container
            function checkScrollIndicator() {
                if (window.innerWidth <= 768 && table.scrollWidth > tableContainer.clientWidth) {
                    if (!tableContainer.querySelector('.scroll-indicator')) {
                        tableContainer.appendChild(scrollIndicator);
                    }
                } else {
                    const indicator = tableContainer.querySelector('.scroll-indicator');
                    if (indicator) {
                        indicator.remove();
                    }
                }
            }
            
            checkScrollIndicator();
            window.addEventListener('resize', checkScrollIndicator);
        }
    }
});

// Helper function to get URL parameters
function getUrlParameter(name) {
    name = name.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]');
    const regex = new RegExp('[\\?&]' + name + '=([^&#]*)');
    const results = regex.exec(location.search);
    return results === null ? '' : decodeURIComponent(results[1].replace(/\+/g, ' '));
}

// Enhanced sorting with smooth transitions
function applySortingTransition() {
    const tableBody = document.querySelector('.properties-table tbody');
    if (tableBody) {
        tableBody.style.transition = 'opacity 0.3s ease';
        tableBody.style.opacity = '0.7';
        
        // Restore opacity after a short delay
        setTimeout(() => {
            tableBody.style.opacity = '1';
        }, 300);
    }
}

// Add CSS for scroll indicator
const scrollIndicatorCSS = `
.scroll-indicator {
    background: linear-gradient(135deg, #17a2b8, #138496);
    color: white;
    padding: 8px 12px;
    text-align: center;
    font-size: 12px;
    border-radius: 0 0 8px 8px;
    margin-bottom: 10px;
    animation: fadeInUp 0.5s ease;
}

.scroll-indicator i {
    margin-right: 5px;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@media (min-width: 769px) {
    .scroll-indicator {
        display: none;
    }
}
`;

// Inject the CSS
const style = document.createElement('style');
style.textContent = scrollIndicatorCSS;
document.head.appendChild(style);

