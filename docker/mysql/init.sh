#!/bin/sh
set -e

mysql -uroot -p"$MYSQL_ROOT_PASSWORD" <<SQL
CREATE DATABASE IF NOT EXISTS ticketflow_test;
GRANT ALL PRIVILEGES ON \`ticketflow\_test%\`.* TO '$MYSQL_USER'@'%';
FLUSH PRIVILEGES;
SQL
