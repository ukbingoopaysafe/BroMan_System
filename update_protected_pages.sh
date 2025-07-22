#!/bin/bash

# Script to add session_check.php to all protected PHP pages
# This script will add the session check include after the translation include

PROTECTED_PAGES=(
    "add_client.php"
    "add_coastal_property.php"
    "add_employee.php"
    "add_other_property.php"
    "add_residential_property.php"
    "coastal_properties.php"
    "commercial_properties.php"
    "delete_client.php"
    "delete_employee.php"
    "edit_client.php"
    "edit_employee.php"
    "employees.php"
    "navbar.php"
    "other_properties.php"
    "residential_properties.php"
    "view_client.php"
    "view_property.php"
    "clients.php"
)

for page in "${PROTECTED_PAGES[@]}"; do
    if [ -f "$page" ]; then
        echo "Updating $page..."
        
        # Check if session_check.php is already included
        if ! grep -q "session_check.php" "$page"; then
            # Find the line with translate.php include and add session_check after it
            if grep -q "translate.php" "$page"; then
                sed -i '/translate\.php/a require_once '\''session_check.php'\''; // Include session timeout check' "$page"
                echo "Added session check to $page"
            else
                echo "Warning: Could not find translate.php include in $page"
            fi
        else
            echo "$page already has session check included"
        fi
    else
        echo "Warning: $page not found"
    fi
done

echo "Script completed."

