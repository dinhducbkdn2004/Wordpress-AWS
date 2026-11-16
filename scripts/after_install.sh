
#!/bin/bash
set -e

PLUGIN_DIR="/var/www/html/wordpress/wp-content/plugins/salon-booking"

# Xóa thư mục dev khỏi production
rm -rf "$PLUGIN_DIR/dev"

# Cấp quyền cho Apache
chown -R ubuntu:www-data "$PLUGIN_DIR"
find "$PLUGIN_DIR" -type d -exec chmod 755 {} \;
find "$PLUGIN_DIR" -type f -exec chmod 644 {} \;
