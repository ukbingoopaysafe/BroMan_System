/**
 * Phone Number Validation and Formatting
 * Handles country code selection and specific length validation.
 */

const COUNTRY_CODES = [
    { code: '+20', name: 'Egypt', flag: '🇪🇬', min: 10, max: 10, example: '10xxxxxxxxx' }, // 010... -> 10...
    { code: '+966', name: 'Saudi Arabia', flag: '🇸🇦', min: 9, max: 9, example: '5xxxxxxxx' },
    { code: '+971', name: 'UAE', flag: '🇦🇪', min: 9, max: 9, example: '5xxxxxxxx' },
    { code: '+965', name: 'Kuwait', flag: '🇰🇼', min: 8, max: 8, example: 'xxxxxxxx' },
    { code: '+974', name: 'Qatar', flag: '🇶🇦', min: 8, max: 8, example: 'xxxxxxxx' },
    { code: '+973', name: 'Bahrain', flag: '🇧🇭', min: 8, max: 8, example: 'xxxxxxxx' },
    { code: '+968', name: 'Oman', flag: '🇴🇲', min: 8, max: 8, example: 'xxxxxxxx' },
    // Arab Countries
    { code: '+964', name: 'Iraq', flag: '🇮🇶', min: 10, max: 11, example: 'xxxxxxxxxx' },
    { code: '+249', name: 'Sudan', flag: '🇸🇩', min: 9, max: 9, example: 'xxxxxxxxx' },
    { code: '+967', name: 'Yemen', flag: '🇾🇪', min: 9, max: 9, example: 'xxxxxxxxx' },
    { code: '+963', name: 'Syria', flag: '🇸🇾', min: 9, max: 9, example: 'xxxxxxxxx' },
    { code: '+961', name: 'Lebanon', flag: '🇱🇧', min: 7, max: 8, example: 'xxxxxxx' },
    { code: '+216', name: 'Tunisia', flag: '🇹🇳', min: 8, max: 8, example: 'xxxxxxxx' },
    { code: '+212', name: 'Morocco', flag: '🇲🇦', min: 9, max: 9, example: 'xxxxxxxxx' },
    { code: '+213', name: 'Algeria', flag: '🇩🇿', min: 9, max: 10, example: 'xxxxxxxxx' },
    { code: '+218', name: 'Libya', flag: '🇱🇾', min: 9, max: 9, example: 'xxxxxxxxx' },
    // European / Other
    { code: '+33', name: 'France', flag: '🇫🇷', min: 9, max: 9, example: 'xxxxxxxxx' },
    { code: '+46', name: 'Sweden', flag: '🇸🇪', min: 7, max: 13, example: 'xxxxxxxxx' },
    { code: '+32', name: 'Belgium', flag: '🇧🇪', min: 9, max: 9, example: 'xxxxxxxxx' },
    { code: '+39', name: 'Italy', flag: '🇮🇹', min: 9, max: 10, example: 'xxxxxxxxx' },
    { code: '+34', name: 'Spain', flag: '🇪🇸', min: 9, max: 9, example: 'xxxxxxxxx' },
    { code: '+43', name: 'Austria', flag: '🇦🇹', min: 10, max: 13, example: 'xxxxxxxxxx' },
    { code: '+48', name: 'Poland', flag: '🇵🇱', min: 9, max: 9, example: 'xxxxxxxxx' },
    { code: '+61', name: 'Australia', flag: '🇦🇺', min: 9, max: 9, example: 'xxxxxxxxx' },

    { code: '+1', name: 'USA/Canada', flag: '🇺🇸', min: 10, max: 10, example: 'xxxxxxxxxx' },
    { code: '+44', name: 'UK', flag: '🇬🇧', min: 10, max: 11, example: 'xxxxxxxxxx' },
    { code: 'other', name: 'Other', flag: '🌍', min: 7, max: 15, example: '' }
];

/**
 * Generates the HTML for the country code select options.
 * @param {string} selectedCode - The code to select by default (e.g. "+20").
 * @returns {string} HTML string of <option> elements.
 */
function getCountryOptionsHtml(selectedCode = '+20') {
    return COUNTRY_CODES.map(country => {
        const isSelected = country.code === selectedCode;
        const display = `${country.flag} ${country.name} (${country.code === 'other' ? 'Other' : country.code})`;
        return `<option value="${country.code}" ${isSelected ? 'selected' : ''} data-min="${country.min}" data-max="${country.max}">${display}</option>`;
    }).join('');
}

/**
 * Validates a phone input based on the selected country code.
 * @param {HTMLInputElement} numberInput - The input element for the phone number.
 * @param {HTMLSelectElement} codeSelect - The select element for the country code.
 */
function validatePhoneLength(numberInput, codeSelect) {
    const selectedOption = codeSelect.selectedOptions[0];
    const countryCode = codeSelect.value;

    // Default to generic if 'other' or missing data
    const min = selectedOption ? parseInt(selectedOption.dataset.min) || 7 : 7;
    const max = selectedOption ? parseInt(selectedOption.dataset.max) || 15 : 15;

    let value = numberInput.value.replace(/[^0-9]/g, ''); // Strip non-numeric

    // Auto-fix: If user pastes "010..." for Egypt (+20), strip the leading 0
    if (countryCode === '+20' && value.startsWith('01')) {
        value = value.substring(1);
        numberInput.value = value;
    }

    if (value.length === 0) {
        // Allow empty if not required (checking 'required' attribute on input is one way, 
        // but typically we conditionally set validity if value exists)
        if (numberInput.hasAttribute('required')) {
            numberInput.setCustomValidity('يرجى إدخال رقم الهاتف');
        } else {
            numberInput.setCustomValidity('');
        }
    } else if (value.length < min || value.length > max) {
        const msg = countryCode === 'other'
            ? `يجب أن يكون الرقم بين ${min} و ${max} رقمًا`
            : `يجب أن يكون الرقم ${min} أرقام لهذا المفتاح`;
        numberInput.setCustomValidity(msg);
    } else {
        numberInput.setCustomValidity('');
    }
}

/**
 * Setup event listeners for a code/number pair.
 * @param {string} codeSelectId 
 * @param {string} numberInputId 
 */
function setupPhoneValidation(codeSelectId, numberInputId) {
    const codeSelect = document.getElementById(codeSelectId);
    const numberInput = document.getElementById(numberInputId);

    if (!codeSelect || !numberInput) return;

    const handler = () => validatePhoneLength(numberInput, codeSelect);

    codeSelect.addEventListener('change', () => {
        // Clear value if switching to/from specific formats? No, keep it.
        // Re-validate immediately
        handler();
        // Update placeholder or visual cues if needed
    });

    numberInput.addEventListener('input', () => {
        // Allow only numbers
        numberInput.value = numberInput.value.replace(/[^0-9]/g, '');
        handler();
    });

    // Initial check
    handler();
}

/**
 * Detects country code and number from a full string.
 * @param {string} fullPhone 
 * @returns {object} { code: string, number: string }
 */
function parsePhoneNumber(fullPhone) {
    if (!fullPhone) return { code: '+20', number: '' };

    // Clean it
    fullPhone = fullPhone.trim();

    // Check strict prefixes
    // Sort by length desc to match longer codes first (+966 before +9)
    const sortedCodes = COUNTRY_CODES.filter(c => c.code !== 'other').sort((a, b) => b.code.length - a.code.length);

    for (let c of sortedCodes) {
        if (fullPhone.startsWith(c.code)) {
            return { code: c.code, number: fullPhone.substring(c.code.length) };
        }
    }

    // Special handling for local Egyptian format 01xxxxxxxxx -> +20, 1xxxxxxxxx
    if (fullPhone.startsWith('01') && fullPhone.length === 11) {
        return { code: '+20', number: fullPhone.substring(1) };
    }

    // Fallback: 'other' or default Egypt with full string?
    // If it starts with '+', treat part of it as code? 
    // Creating a "custom" code situation is tricky with fixed dropdown.
    // We will select 'other' and put the whole string (stripped of +?) in number?
    // Or just default to Other.
    return { code: 'other', number: fullPhone.replace(/^\+/, '') };
}
