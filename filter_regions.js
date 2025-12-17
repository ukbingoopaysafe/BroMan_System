document.addEventListener('DOMContentLoaded', function () {
    const governorateSelect = document.getElementById('governorate');
    const districtSelect = document.getElementById('district'); // Ensure this ID exists in HTML

    // Store the pre-selected value if any (from server-side rendering or URL params)
    const currentDistrict = districtSelect ? districtSelect.getAttribute('data-selected') : '';

    if (governorateSelect && districtSelect) {

        // Function to load districts
        function loadDistricts(governorate, selectedDistrict = '') {
            districtSelect.innerHTML = '<option value="">' + (districtSelect.getAttribute('data-loading-text') || 'Loading...') + '</option>';
            districtSelect.disabled = true;

            if (!governorate) {
                districtSelect.innerHTML = '<option value="">' + (districtSelect.getAttribute('data-default-text') || 'Choose Governorate First') + '</option>';
                districtSelect.disabled = true;
                return;
            }

            fetch('get_districts.php?governorate=' + encodeURIComponent(governorate))
                .then(response => response.json())
                .then(data => {
                    const defaultText = districtSelect.getAttribute('data-default-text') || 'All Regions';
                    districtSelect.innerHTML = '<option value="">' + defaultText + '</option>';

                    if (data.length > 0) {
                        data.forEach(district => {
                            const option = document.createElement('option');
                            option.value = district;
                            option.textContent = district;
                            if (district === selectedDistrict) {
                                option.selected = true;
                            }
                            districtSelect.appendChild(option);
                        });
                        districtSelect.disabled = false;
                    } else {
                        districtSelect.innerHTML = '<option value="">' + (districtSelect.getAttribute('data-no-data-text') || 'No regions found') + '</option>';
                    }
                })
                .catch(error => {
                    console.error('Error fetching districts:', error);
                    districtSelect.innerHTML = '<option value="">Error loading</option>';
                });
        }

        // Event Listener
        governorateSelect.addEventListener('change', function () {
            loadDistricts(this.value);
        });

        // Initial Load (if governorate is already selected)
        if (governorateSelect.value) {
            loadDistricts(governorateSelect.value, currentDistrict);
        } else {
            // If no governorate selected, disable district
            // Maybe user wants "All Regions" for "All Governorates"? 
            // User said: "logic .. must select governorate first"
            // So disable it.
            const promptText = districtSelect.getAttribute('data-prompt-text') || 'Select Governorate First';
            districtSelect.innerHTML = '<option value="">' + promptText + '</option>';
            districtSelect.disabled = true;
        }
    }
});
