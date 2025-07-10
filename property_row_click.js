// Clickable rows functionality
document.addEventListener('DOMContentLoaded', function() {
    // Add click event listeners to all property rows
    const propertyRows = document.querySelectorAll('.property-row');
    
    propertyRows.forEach(function(row) {
        // Make the row cursor pointer
        row.style.cursor = 'pointer';
        
        // Add click event listener
        row.addEventListener('click', function(event) {
            // Check if the click was on an action button or its children
            const isActionButton = event.target.closest('.table-actions, .btn-edit, .btn-delete');
            
            // If not clicking on action buttons, navigate to property details
            if (!isActionButton) {
                const propertyId = this.getAttribute('data-property-id');
                if (propertyId) {
                    window.location.href = 'view_property.php?id=' + propertyId;
                }
            }
        });
        
        // Add hover effects
        row.addEventListener('mouseenter', function() {
            this.style.backgroundColor = '#f8f9fa';
            this.style.transform = 'translateY(-1px)';
            this.style.boxShadow = '0 2px 4px rgba(0,0,0,0.1)';
        });
        
        row.addEventListener('mouseleave', function() {
            this.style.backgroundColor = '';
            this.style.transform = '';
            this.style.boxShadow = '';
        });
    });
});

