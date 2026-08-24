# Security and web-server setup

This application can submit live statutory filings and stores sensitive gateway data. Do not expose it as an ordinary public directory.

## Minimum controls

- Protect the **entire application directory** with authentication.
- Use HTTPS only.
- Disable directory listing.
- Keep `.htpasswd` or equivalent credentials outside the web root.
- Do not commit `config.local.php`, `storage/filing.php`, transaction state, envelopes, responses or logs.
- Do not use the Companies House presenter code as the admin password.
- Do not grant world-writable permissions.
- Back up runtime records privately.

The repository intentionally does not include a live `.htaccess` file.

## Apache: virtual-host configuration

A server-level configuration is preferred:

```apache
<Directory "/var/www/CompaniesHouse-iXBRL">
    Options -Indexes
    AllowOverride None

    AuthType Basic
    AuthName "Companies House filing"
    AuthUserFile "/etc/apache2/private/companies-house.htpasswd"
    Require valid-user

    <FilesMatch "^(config\.local\.php|filing\.php)$">
        Require all denied
    </FilesMatch>
</Directory>
```

Create/update the password file outside the web root:

```bash
htpasswd -cB /etc/apache2/private/companies-house.htpasswd admin
```

Omit `-c` when the file already exists and you do not want to replace it.

## Apache: shared hosting with `.htaccess`

Where the host permits `.htaccess`, create your own file in the project directory and use the correct absolute password-file path:

```apache
Options -Indexes

AuthType Basic
AuthName "Companies House filing"
AuthUserFile /absolute/path/outside/public_html/.htpasswd
Require valid-user
```

Do not copy an `AuthUserFile` path from another server. Confirm the path with the hosting provider.

## Nginx

Example location block:

```nginx
location /companies-house/ {
    autoindex off;
    auth_basic "Companies House filing";
    auth_basic_user_file /etc/nginx/private/companies-house.htpasswd;

    try_files $uri $uri/ =404;
}

location ~ ^/companies-house/(config\.local\.php|storage/.*\.php)$ {
    deny all;
}
```

The PHP-FPM location/configuration must still process the application PHP files correctly. Adapt the paths to the actual server layout.

## File permissions

Prefer ownership and group permissions over `777`:

```bash
chown -R deploy:www-data /var/www/CompaniesHouse-iXBRL
find /var/www/CompaniesHouse-iXBRL -type d -exec chmod 0750 {} \;
find /var/www/CompaniesHouse-iXBRL -type f -exec chmod 0640 {} \;
chmod 0770 /var/www/CompaniesHouse-iXBRL/out
chmod 0770 /var/www/CompaniesHouse-iXBRL/out/logs
chmod 0770 /var/www/CompaniesHouse-iXBRL/storage
chmod 0770 /var/www/CompaniesHouse-iXBRL/storage/filing-backups
chmod 0660 /var/www/CompaniesHouse-iXBRL/storage/filing.php
chmod 0660 /var/www/CompaniesHouse-iXBRL/storage/transaction_id.txt
```

The project root and PHP source files do not need to be writable by the web-server user.
