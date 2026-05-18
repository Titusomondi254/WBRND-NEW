@echo off
"C:\xampp\mysql\bin\mysql.exe" -u root walbrand_properties -e "ALTER TABLE users ADD COLUMN IF NOT EXISTS profile_picture VARCHAR(255);"
echo Column added successfully!
pause