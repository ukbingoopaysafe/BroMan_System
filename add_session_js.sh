#!/bin/bash

# Script to add session timeout JavaScript to all protected PHP pages
# This script will add the JavaScript include before the closing </head> tag

PROTECTED_PAGES=(
    "add_client.php"
    "add_coastal_property.php"
    "add_employee.php"
    "add_other_property.php"
    "add_residential_property.php"
    "coastal_properties.php"
    "commercial_properties.php"
    "edit_client.php"
    "edit_employee.php"
    "residential_properties.php"
    "view_property.php"
)

for page in "${PROTECTED_PAGES[@]}"; do
    if [ -f "$page" ]; then
        echo "Updating $page..."
        
        # Check if session_timeout.js is already included
        if ! grep -q "session_timeout.js" "$page"; then
            # Find the line with </head> and add JavaScript before it
            if grep -q "</head>" "$page"; then
                sed -i 's|</head>|    <script src="session_timeout.js"></script>\n</head>|' "$page"
                echo "Added session timeout JavaScript to $page"
            else
                echo "Warning: Could not find </head> tag in $page"
            fi
        else
            echo "$page already has session timeout JavaScript included"
        fi
    else
        echo "Warning: $page not found"
    fi
done

echo "Script completed."

