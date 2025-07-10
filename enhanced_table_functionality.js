// Enhanced JavaScript for Property Table Filtering and Sorting

class PropertyTableManager {
    constructor() {
        this.currentSort = { column: null, direction: 'asc' };
        this.filters = {};
        this.selectedRows = new Set();
        this.init();
    }

    init() {
        this.initSorting();
        this.initFiltering();
        this.initBulkSelection();
        this.initAdvancedFilters();
        this.initQuickFilters();
    }

    // ================================================
    // Sorting Functionality
    // ================================================
    
    initSorting() {
        const sortableHeaders = document.querySelectorAll('.sortable-header');
        sortableHeaders.forEach(header => {
            header.addEventListener('click', (e) => {
                e.preventDefault();
                const column = header.dataset.column;
                this.sortTable(column);
            });
        });
    }

    sortTable(column) {
        const table = document.querySelector('.properties-table tbody');
        const rows = Array.from(table.querySelectorAll('tr'));
        
        // Determine sort direction
        if (this.currentSort.column === column) {
            this.currentSort.direction = this.currentSort.direction === 'asc' ? 'desc' : 'asc';
        } else {
            this.currentSort.direction = 'asc';
        }
        this.currentSort.column = column;

        // Update header indicators
        this.updateSortIndicators();

        // Sort rows
        rows.sort((a, b) => {
            const aValue = this.getCellValue(a, column);
            const bValue = this.getCellValue(b, column);
            
            let comparison = 0;
            if (this.isNumeric(aValue) && this.isNumeric(bValue)) {
                comparison = parseFloat(aValue) - parseFloat(bValue);
            } else {
                comparison = aValue.localeCompare(bValue, undefined, { numeric: true });
            }
            
            return this.currentSort.direction === 'asc' ? comparison : -comparison;
        });

        // Re-append sorted rows
        rows.forEach(row => table.appendChild(row));
        
        // Add visual feedback
        this.addSortAnimation();
    }

    getCellValue(row, column) {
        const cell = row.querySelector(`.cell-${column}`);
        if (!cell) return '';
        
        // Handle special cases
        if (column === 'price' || column === 'area') {
            const text = cell.textContent.trim();
            const numbers = text.match(/[\d,]+/g);
            return numbers ? numbers[0].replace(/,/g, '') : '0';
        }
        
        return cell.textContent.trim();
    }

    isNumeric(value) {
        return !isNaN(parseFloat(value)) && isFinite(value);
    }

    updateSortIndicators() {
        // Reset all indicators
        document.querySelectorAll('.sort-indicator').forEach(indicator => {
            indicator.className = 'sort-indicator none';
        });
        
        // Update current column indicator
        const currentHeader = document.querySelector(`[data-column="${this.currentSort.column}"]`);
        if (currentHeader) {
            const indicator = currentHeader.querySelector('.sort-indicator');
            if (indicator) {
                indicator.className = `sort-indicator ${this.currentSort.direction}`;
            }
            currentHeader.classList.add('sorted');
        }
        
        // Remove sorted class from other headers
        document.querySelectorAll('.sortable-header').forEach(header => {
            if (header.dataset.column !== this.currentSort.column) {
                header.classList.remove('sorted');
            }
        });
    }

    addSortAnimation() {
        const table = document.querySelector('.properties-table');
        table.style.opacity = '0.7';
        setTimeout(() => {
            table.style.opacity = '1';
        }, 150);
    }

    // ================================================
    // Advanced Filtering
    // ================================================
    
    initFiltering() {
        const filterForm = document.querySelector('.filter-section form');
        if (filterForm) {
            // Add real-time filtering for text inputs
            const textInputs = filterForm.querySelectorAll('input[type="text"], input[type="number"]');
            textInputs.forEach(input => {
                let timeout;
                input.addEventListener('input', () => {
                    clearTimeout(timeout);
                    timeout = setTimeout(() => {
                        this.applyFilters();
                    }, 500);
                });
            });

            // Add change listeners for select elements
            const selects = filterForm.querySelectorAll('select');
            selects.forEach(select => {
                select.addEventListener('change', () => {
                    this.applyFilters();
                });
            });
        }
    }

    applyFilters() {
        const rows = document.querySelectorAll('.properties-table tbody tr');
        let visibleCount = 0;

        rows.forEach(row => {
            const isVisible = this.rowMatchesFilters(row);
            row.style.display = isVisible ? '' : 'none';
            if (isVisible) visibleCount++;
        });

        this.updateResultsCount(visibleCount);
        this.updateNoResultsMessage(visibleCount === 0);
    }

    rowMatchesFilters(row) {
        const filters = this.getActiveFilters();
        
        for (const [key, value] of Object.entries(filters)) {
            if (!value) continue;
            
            const cell = row.querySelector(`.cell-${key}`);
            if (!cell) continue;
            
            const cellText = cell.textContent.toLowerCase().trim();
            const filterValue = value.toLowerCase().trim();
            
            if (key === 'search') {
                // Search across all cells
                const allText = Array.from(row.querySelectorAll('td'))
                    .map(td => td.textContent.toLowerCase())
                    .join(' ');
                if (!allText.includes(filterValue)) return false;
            } else if (key.includes('min_') || key.includes('max_')) {
                // Handle numeric range filters
                if (!this.checkNumericFilter(cellText, key, filterValue)) return false;
            } else {
                // Exact or partial match
                if (!cellText.includes(filterValue)) return false;
            }
        }
        
        return true;
    }

    getActiveFilters() {
        const filters = {};
        const form = document.querySelector('.filter-section form');
        if (!form) return filters;

        const inputs = form.querySelectorAll('input, select');
        inputs.forEach(input => {
            if (input.value && input.value.trim()) {
                filters[input.name] = input.value.trim();
            }
        });

        return filters;
    }

    checkNumericFilter(cellText, filterKey, filterValue) {
        const cellNumber = parseFloat(cellText.replace(/[^\d.]/g, ''));
        const filterNumber = parseFloat(filterValue);
        
        if (isNaN(cellNumber) || isNaN(filterNumber)) return true;
        
        if (filterKey.startsWith('min_')) {
            return cellNumber >= filterNumber;
        } else if (filterKey.startsWith('max_')) {
            return cellNumber <= filterNumber;
        }
        
        return true;
    }

    updateResultsCount(count) {
        const countElement = document.querySelector('.results-count .count-number');
        if (countElement) {
            countElement.textContent = count;
        }
    }

    updateNoResultsMessage(show) {
        let noResultsDiv = document.querySelector('.no-results-message');
        
        if (show && !noResultsDiv) {
            noResultsDiv = document.createElement('div');
            noResultsDiv.className = 'no-results-message no-properties';
            noResultsDiv.innerHTML = `
                <i class="fas fa-search"></i>
                <h3>No properties match your filters</h3>
                <p>Try adjusting your search criteria or clearing some filters.</p>
            `;
            document.querySelector('.table-responsive').after(noResultsDiv);
        } else if (!show && noResultsDiv) {
            noResultsDiv.remove();
        }
        
        const table = document.querySelector('.table-responsive');
        if (table) {
            table.style.display = show ? 'none' : 'block';
        }
    }

    // ================================================
    // Bulk Selection
    // ================================================
    
    initBulkSelection() {
        const selectAllCheckbox = document.querySelector('.select-all-checkbox');
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', (e) => {
                this.selectAll(e.target.checked);
            });
        }

        const bulkCheckboxes = document.querySelectorAll('.bulk-checkbox');
        bulkCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', (e) => {
                this.toggleRowSelection(e.target.value, e.target.checked);
            });
        });
    }

    selectAll(checked) {
        const visibleCheckboxes = document.querySelectorAll('.bulk-checkbox:not([style*="display: none"])');
        visibleCheckboxes.forEach(checkbox => {
            checkbox.checked = checked;
            this.toggleRowSelection(checkbox.value, checked);
        });
    }

    toggleRowSelection(rowId, selected) {
        if (selected) {
            this.selectedRows.add(rowId);
        } else {
            this.selectedRows.delete(rowId);
        }
        
        this.updateBulkActionsBar();
        this.updateSelectAllState();
    }

    updateBulkActionsBar() {
        const bulkBar = document.querySelector('.bulk-actions-bar');
        const selectionInfo = document.querySelector('.bulk-selection-info');
        
        if (this.selectedRows.size > 0) {
            bulkBar?.classList.add('show');
            if (selectionInfo) {
                selectionInfo.textContent = `${this.selectedRows.size} properties selected`;
            }
        } else {
            bulkBar?.classList.remove('show');
        }
    }

    updateSelectAllState() {
        const selectAllCheckbox = document.querySelector('.select-all-checkbox');
        const visibleCheckboxes = document.querySelectorAll('.bulk-checkbox:not([style*="display: none"])');
        const checkedVisible = Array.from(visibleCheckboxes).filter(cb => cb.checked);
        
        if (selectAllCheckbox) {
            selectAllCheckbox.checked = checkedVisible.length === visibleCheckboxes.length && visibleCheckboxes.length > 0;
            selectAllCheckbox.indeterminate = checkedVisible.length > 0 && checkedVisible.length < visibleCheckboxes.length;
        }
    }

    // ================================================
    // Advanced Filters Panel
    // ================================================
    
    initAdvancedFilters() {
        const toggleButton = document.querySelector('.advanced-filters-toggle');
        if (toggleButton) {
            toggleButton.addEventListener('click', () => {
                this.toggleAdvancedFilters();
            });
        }
    }

    toggleAdvancedFilters() {
        const panel = document.querySelector('.advanced-filters');
        const toggleButton = document.querySelector('.advanced-filters-toggle');
        
        if (panel && toggleButton) {
            const isVisible = panel.classList.contains('show');
            
            if (isVisible) {
                panel.classList.remove('show');
                toggleButton.innerHTML = '<i class="fas fa-sliders-h"></i> Advanced Filters';
            } else {
                panel.classList.add('show');
                toggleButton.innerHTML = '<i class="fas fa-times"></i> Hide Advanced Filters';
            }
        }
    }

    // ================================================
    // Quick Filters
    // ================================================
    
    initQuickFilters() {
        const quickFilters = document.querySelectorAll('.filter-chip');
        quickFilters.forEach(chip => {
            chip.addEventListener('click', (e) => {
                e.preventDefault();
                this.applyQuickFilter(chip);
            });
        });
    }

    applyQuickFilter(chip) {
        // Remove active class from all chips
        document.querySelectorAll('.filter-chip').forEach(c => c.classList.remove('active'));
        
        // Add active class to clicked chip
        chip.classList.add('active');
        
        // Apply the filter based on chip data
        const filterType = chip.dataset.filter;
        const filterValue = chip.dataset.value;
        
        if (filterType && filterValue) {
            const input = document.querySelector(`[name="${filterType}"]`);
            if (input) {
                input.value = filterValue;
                this.applyFilters();
            }
        }
    }

    // ================================================
    // Export Functionality
    // ================================================
    
    exportSelected(format) {
        if (this.selectedRows.size === 0) {
            alert('Please select properties to export');
            return;
        }
        
        const selectedData = this.getSelectedRowsData();
        
        switch (format) {
            case 'csv':
                this.exportToCSV(selectedData);
                break;
            case 'excel':
                this.exportToExcel(selectedData);
                break;
            case 'pdf':
                this.exportToPDF(selectedData);
                break;
        }
    }

    getSelectedRowsData() {
        const data = [];
        const headers = Array.from(document.querySelectorAll('.properties-table thead th'))
            .map(th => th.textContent.trim());
        
        this.selectedRows.forEach(rowId => {
            const row = document.querySelector(`tr[data-id="${rowId}"]`);
            if (row) {
                const rowData = Array.from(row.querySelectorAll('td'))
                    .map(td => td.textContent.trim());
                data.push(rowData);
            }
        });
        
        return { headers, data };
    }

    exportToCSV(data) {
        const csv = [data.headers, ...data.data]
            .map(row => row.map(cell => `"${cell}"`).join(','))
            .join('\\n');
        
        const blob = new Blob([csv], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'properties.csv';
        a.click();
        window.URL.revokeObjectURL(url);
    }

    // ================================================
    // Utility Methods
    // ================================================
    
    clearAllFilters() {
        const form = document.querySelector('.filter-section form');
        if (form) {
            form.reset();
            this.applyFilters();
        }
        
        // Clear quick filter selections
        document.querySelectorAll('.filter-chip').forEach(chip => {
            chip.classList.remove('active');
        });
    }

    resetTable() {
        this.clearAllFilters();
        this.selectedRows.clear();
        this.updateBulkActionsBar();
        this.currentSort = { column: null, direction: 'asc' };
        this.updateSortIndicators();
    }
}

// Initialize the table manager when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    window.propertyTableManager = new PropertyTableManager();
});

// Additional utility functions for advanced features
function toggleAdvancedFilters() {
    if (window.propertyTableManager) {
        window.propertyTableManager.toggleAdvancedFilters();
    }
}

function exportProperties(format) {
    if (window.propertyTableManager) {
        window.propertyTableManager.exportSelected(format);
    }
}

function clearFilters() {
    if (window.propertyTableManager) {
        window.propertyTableManager.clearAllFilters();
    }
}

