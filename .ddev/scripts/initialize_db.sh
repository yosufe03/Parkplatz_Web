#!/bin/bash
set -e

DB_FLAG_FILE="/var/tmp/db_initialized"

if [ -f "$DB_FLAG_FILE" ]; then
  echo ">>> Database already initialized, skipping import."
  exit 0
fi

echo ">>> Importing initial database schema..."
mysql -u root -proot db < /var/www/html/sql/parkshare_schema.sql

echo ">>> Adding sample data..."
# You can add optional seeding here if you want:
# mysql -u root -proot db < /var/www/html/sql/seed_data.sql

touch "$DB_FLAG_FILE"
echo ">>> Database initialization complete!"
