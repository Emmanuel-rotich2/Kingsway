#!/bin/bash

# Quick migration runner for XAMPP
echo "Running Student ID Card migration..."
echo "Enter your XAMPP MySQL root password when prompted"
echo ""

/opt/lampp/bin/mysql -u root -p KingsWayAcademy < database/student_id_card_migration.sql

if [ $? -eq 0 ]; then
    echo ""
    echo "✓ Migration completed successfully!"
else
    echo ""
    echo "✗ Migration failed. Please check the error above."
fi
