# Contact Manager

A personal contact manager web application built using the LAMP stack.

## Live Links

- **Live application**: [https://contact-manager.poosd.dev](https://contact-manager.poosd.dev)
- **SwaggerHub API Documentation:** [https://app.swaggerhub.com/apis-docs/poosd-2bf/contact-manager-api](https://app.swaggerhub.com/apis-docs/poosd-2bf/contact-manager-api)

## Features

- Register, sign in, and sign out with session-based authentication.
- Add, edit, and delete contacts in your own contact list.
- Search by partial matches on first name, last name, company, email, or phone number.
- Load more results when a search spans multiple pages.

## Tech Stack

- Linux
- Apache
- MySQL 8.0
- PHP 8.3
- Twig 3
- Tailwind CSS 4
- JavaScript

## Getting Started

Local setup on Ubuntu 24.04 with Apache and MySQL.

### Prerequisites

- Git
- PHP 8.3 CLI with `pdo_mysql`, `mbstring`, `dom`, `xml`, and `xmlwriter` extensions
- Apache 2.4 configured to run PHP 8.3
- MySQL 8.0
- Composer 2
- Node.js 24 and npm

### Install Dependencies

```bash
sudo mkdir -p /var/www/contact-manager
sudo chown "$USER":"$(id -gn)" /var/www/contact-manager
git clone https://github.com/milesrack/contact-manager.git /var/www/contact-manager
cd /var/www/contact-manager
composer install
npm ci
```

### Configure the Environment

```bash
cp .env.example .env
```

Set these values in `.env`, replacing `change_me` with your local database password:

```dotenv
APP_ENV=development
APP_URL=http://localhost

DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=contact_manager
DB_USER=contact_manager
DB_PASSWORD=change_me
```

### Create the Database

Open MySQL as an administrator:

```bash
sudo mysql
```

Use the same password you set in `.env`:

```sql
CREATE DATABASE contact_manager CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'contact_manager'@'127.0.0.1' IDENTIFIED BY 'change_me';
GRANT ALL PRIVILEGES ON contact_manager.* TO 'contact_manager'@'127.0.0.1';
EXIT;
```

From the project directory, create the tables and build the CSS:

```bash
composer migrate
npm run build
```

### Configure Apache

Create `/etc/apache2/sites-available/contact-manager.conf`:

```apache
<VirtualHost *:80>
    ServerName localhost
    DocumentRoot /var/www/contact-manager/public

    <Directory /var/www/contact-manager/public>
        Options FollowSymLinks
        AllowOverride None
        Require all granted
        FallbackResource /index.php
    </Directory>

    <Directory /var/www/contact-manager/public/api>
        FallbackResource /api/index.php
    </Directory>
</VirtualHost>
```

Enable the site in place of Ubuntu's default Apache site:

```bash
sudo a2dissite 000-default
sudo a2ensite contact-manager
sudo apache2ctl configtest
```

### Running Locally

```bash
sudo systemctl restart apache2
```

Navigate to [http://localhost](http://localhost).

## Production Deployment

Contact Manager owns `contact-manager.poosd.dev`. The apex domain `poosd.dev`
and the other subdomains belong to separate applications on the shared server.
Do not add them as aliases to Contact Manager's virtual hosts.

GitHub Actions deploys successful `main` CI builds using the `production`
environment. Configure these environment variables:

| Variable             | Value                                              |
| -------------------- | -------------------------------------------------- |
| `DEPLOY_DOMAIN`      | `contact-manager.poosd.dev`                        |
| `DEPLOY_HOST`        | The server's SSH hostname or IP address            |
| `DEPLOY_KNOWN_HOSTS` | The server's verified SSH host key (`known_hosts`) |

Store the deploy user's private key in the `DEPLOY_SSH_KEY` environment secret.
The repository website should be `https://contact-manager.poosd.dev`.

Releases live in `/var/www/contact-manager/releases/<commit-sha>`;
`/var/www/contact-manager/current` points to the active release. Keep the runtime
environment in `/var/www/contact-manager/shared/.env` with
`APP_ENV=production` and `APP_URL=https://contact-manager.poosd.dev`. Keep database
credentials out of Git. The deploy user also needs read access to
`/etc/contact-manager/migration.env` for database migrations.

### Apache and TLS

Point the subdomain's DNS to the deployment server. Its HTTP virtual host serves
ACME challenges from `/var/www/letsencrypt` and redirects other requests to the
same subdomain over HTTPS. Install the HTTP configuration first:

```bash
sudo install -d -m 755 /var/www/letsencrypt/.well-known/acme-challenge
sudo install -m 644 deploy/apache/contact-manager.conf /etc/apache2/sites-available/contact-manager.conf
sudo a2ensite contact-manager
sudo apache2ctl configtest
sudo systemctl reload apache2
```

Issue a dedicated certificate before enabling the HTTPS virtual host:

```bash
sudo certbot certonly --webroot -w /var/www/letsencrypt \
  --cert-name contact-manager.poosd.dev -d contact-manager.poosd.dev \
  --deploy-hook 'systemctl reload apache2'
sudo install -m 644 deploy/apache/contact-manager-le-ssl.conf /etc/apache2/sites-available/contact-manager-le-ssl.conf
sudo a2ensite contact-manager-le-ssl
sudo apache2ctl configtest
sudo systemctl reload apache2
sudo certbot renew --cert-name contact-manager.poosd.dev --dry-run
```

When migrating an existing installation, disable its previous Contact Manager
virtual hosts before reloading so each hostname has only one enabled HTTP and
HTTPS virtual host. Keep the other applications' sites and certificates enabled.
Apache configuration changes require these administrator steps; the deployment
workflow uploads application releases only.

Deployment verification requires `/login` to return the Contact Manager login
page with HTTP 200 and `/api/contacts` to return the unauthenticated JSON error
with HTTP 401. It does not follow redirects, so a redirect to another application
cannot pass the checks.

## Project Structure

```text
contact-manager/
├── .github/workflows/   # CI and deployment
├── assets/css/          # Tailwind source styles
├── bin/                 # Database migration runner
├── config/              # Application, database, and Twig configuration
├── database/migrations/ # SQL schema migrations
├── deploy/apache/       # Production Apache virtual hosts
├── public/              # Apache document root
│   ├── api/index.php    # API entry point
│   ├── assets/          # JavaScript and generated CSS
│   └── index.php        # Page entry point
├── src/                 # Controllers, repositories, and database connection
├── templates/           # Twig pages and shared components
├── tests/               # PHP tests
├── .env.example         # Environment configuration template
├── composer.json        # PHP dependencies and commands
├── package.json         # Frontend dependencies and commands
├── openapi.yaml         # API specification
├── phpunit.xml          # Test configuration
├── CONTRIBUTING.md      # Developer guide
├── LICENSE
└── README.md
```

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md) for development and testing instructions.

## AI Assistance Disclosure

This project was developed with assistance from generative AI tools:

- **Tool**: ChatGPT, GPT-5.6 Sol (OpenAI, chatgpt.com)
- **Dates**: 27 August to 25 September 2026
- **Scope**: Repository setup, troubleshooting, debugging, writing tests, and advising on best practices
- **Use**: Configuring the repository and development tooling; troubleshooting setup and deployment issues; debugging technical errors; writing tests; advising on best practices

All AI-generated code was reviewed, tested, and modified to meet assignment requirements. Final implementation reflects my understanding of the concepts.

## Licence

Licensed under the [MIT License](LICENSE).
