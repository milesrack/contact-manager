# Contact Manager

## Project Overview

Contact manager is a web-based application built with the LAMP stack that allows users to securely create accounts and manage their own private contacts. The application provides user authentication, contact management, server-side search, and a REST-style API for handling application data.

## Features

- User registration, login, and logout
- Session-based user authentication
- Private contact lists for each user
- Create new contacts
- Update existing contacts
- Delete contacts
- Search contacts by first name, last name, company, email, or phone number
- Partial contact matching
- Paginated contact search results
- Server-side input validation
- JSON API responses
- REST-style API endpoints documented with OpenAPI and SwaggerHub

## Live Links

- **Deployed Application:** [https://poosd.dev](https://poosd.dev)
- **SwaggerHub API Documentation:**

## Tech Stack

### Backend

- **Linux** - production server environment
- **Apache** - web server and API request routing
- **MySQL 8.0** - relational database
- **PHP 8.3** - backend application abd API logic
- **PDO / PDO MySQL** - database access
- **Twig 3** - PHP templating
- **PHP dotenv** - environment-variable configuration

### Frontend

- **HTML / Twig** - application interface and reusable templates
- **JavaScript** - client-side authentication, API requests, and contact interactions
- **Tailwind CSS 4** - application styling
- **Node.js 24** - frontend tooling environment
- **npm** - frontend dependency and build management

### API and Documentation

- **REST-style API** - authentication and contact operations
- **JSON** - API request and response format
- **OpenAPI 3.0.3** - API specification
- **SwaggerHub** - hosted API documentation

### Development and Testing

- **Composer 2** - PHP dependency and script management
- **PHPUnit 12** - automated PHP testing
- **Guzzle 8** - HTTP client used for API integration testing
- **PHPStan 2** - static analysis
- **PHP CS Fixer 3** - PHP code formatting
- **PHP Parallel Lint** - PHP syntax checking
- **ESLint 10** - JavaScript linting
- **Prettier 3** - project formatting
- **GitHub Actions** - continuous integration and deployment
- **Dependabot** - automated dependency update monitoring

## Project Structure

```text
contact-manager/
├── .github/
│   ├── ISSUE_TEMPLATE/
│   │   ├── bug.yml                         # Bug report issue template
│   │   ├── config.yml                      # GitHub issue configuration
│   │   └── task.yml                        # Development task issue template
│   ├── workflows/
│   │   ├── ci.yml                          # Continuous integration workflow
│   │   └── deploy.yml                      # Production deployment workflow
│   ├── dependabot.yml                      # Automated dependency update configuration
│   └── pull_request_template.md             # Pull request template
│
├── assets/
│   └── css/
│       └── app.css                          # Tailwind source stylesheet
│
├── bin/
│   └── migrate.php                          # Database migration runner
│
├── config/
│   ├── app.php                              # Application configuration
│   ├── bootstrap.php                        # Composer and environment bootstrap
│   ├── database.php                         # Database configuration
│   └── twig.php                             # Twig template engine configuration
│
├── database/
│   └── migrations/
│       └── 001_create_initial_schema.sql    # Users and contacts database schema
│
├── deploy/
│   └── apache/
│       ├── contact-manager-le-ssl.conf      # HTTPS Apache virtual host
│       └── contact-manager.conf             # HTTP Apache virtual host
│
├── public/
│   ├── api/
│   │   └── index.php                        # API front controller and router
│   │
│   ├── assets/
│   │   ├── css/
│   │   │   └── app.css                      # Generated Tailwind CSS output
│   │   │
│   │   └── js/
│   │       ├── api.js                       # Shared API request utilities
│   │       ├── auth.js                      # Authentication interface logic
│   │       └── contacts.js                  # Contact interface logic
│   │
│   └── index.php                            # Web application front controller
│
├── src/
│   ├── AuthController.php                   # Authentication application logic
│   ├── ContactController.php                # Contact validation and application logic
│   ├── ContactRepository.php                # Contact database operations
│   ├── Database.php                         # PDO database connection
│   └── UserRepository.php                   # User database operations
│
├── templates/
│   ├── auth/
│   │   ├── login.html.twig                  # Login page
│   │   ├── nav.html.twig                    # Authentication navigation
│   │   ├── password.html.twig               # Reusable password input
│   │   └── register.html.twig               # Registration page
│   │
│   ├── components/
│   │   ├── contact-card.html.twig           # Individual contact display
│   │   ├── contact-form.html.twig           # Create/edit contact form
│   │   ├── delete-modal.html.twig           # Contact deletion confirmation
│   │   └── discard-modal.html.twig          # Unsaved-change confirmation
│   │
│   ├── contacts/
│   │   └── index.html.twig                  # Main contacts page
│   │
│   ├── errors/
│   │   └── 404.html.twig                    # Not-found page
│   │
│   └── base.html.twig                       # Base application template
│
├── tests/
│   ├── ApiIndexTest.php                     # End-to-end API and frontend route tests
│   ├── ContactControllerTest.php            # Contact controller tests
│   ├── ContactRepositoryTest.php            # Contact repository tests
│   ├── DatabaseConnectionTest.php           # Database connection tests
│   └── UserRepositoryTest.php               # User repository tests
│
├── .editorconfig                            # Editor formatting configuration
├── .env.example                             # Example environment configuration
├── .gitattributes                           # Git file handling configuration
├── .gitignore                               # Files excluded from Git
├── .nvmrc                                   # Node.js version
├── .php-cs-fixer.dist.php                   # PHP CS Fixer configuration
├── .php-version                             # PHP version
├── .prettierignore                          # Files excluded from Prettier
├── .prettierrc                              # Prettier configuration
├── CONTRIBUTING.md                          # Development and contribution workflow
├── LICENSE                                  # Project license
├── README.md                                # Application documentation
├── SECURITY.md                              # Vulnerability reporting policy
├── composer.json                            # PHP dependencies and scripts
├── composer.lock                            # Locked PHP dependency versions
├── eslint.config.mjs                        # ESLint configuration
├── openapi.yaml                             # OpenAPI API specification
├── package-lock.json                        # Locked npm dependency versions
├── package.json                             # Frontend dependencies and scripts
├── phpstan.neon                             # PHPStan static-analysis configuration
└── phpunit.xml                              # PHPUnit test configuration
```

> `vendor/`, `node_modules/`, test caches, local environment files, and other generated or ignored files are not shown. `public/assets/css/app.css` is generated from `assets/css/app.css` during the frontend build and is not committed to Git.

## API Overview

The application exposes a REST-style JSON API for authentication and contact management. Apache routes API requests through `public/api/index.php`, which acts as the API front controller.

The front controller handles HTTP-specific responsibilities such as:

- Determining the requested path
- Checking the HTTP method
- Starting and reading the user session
- Parsing query parameters
- Parsing JSON request bodies
- Extracting contact IDs from request paths
- Routing requests to the appropriate controller
- Setting HTTP response status codes and headers
- Returning JSON responses

Application-level logic is handled by controller classes:

- `AuthController` handles registration, login, logout, authentication validation, password hashing, and session management.
- `ContactController` validates contact data and handles contact-related application logic.

Controllers use repositories for database access:

- `UserRepository` handles user database operations.
- `ContactRepository` handles contact creation, updating, deletion, searching, user isolation, and pagination.

Database communication is performed through PDO using prepared statements.

### Request Flow

```text
Client
  ↓
Apache
  ↓
public/api/index.php
  ↓
AuthController / ContactController
  ↓
UserRepository / ContactRepository
  ↓
PDO
  ↓
MySQL
```

### API Endpoints

| Method   | Endpoint                     | Description                                          |
| -------- | ---------------------------- | ---------------------------------------------------- |
| `POST`   | `/api/auth/register`         | Create a new user account                            |
| `POST`   | `/api/auth/login`            | Authenticate a user and start a session              |
| `POST`   | `/api/auth/logout`           | End the current user session                         |
| `GET`    | `/api/contacts`              | Search or retrieve the authenticated user's contacts |
| `POST`   | `/api/contacts`              | Create a new contact                                 |
| `PATCH`  | `/api/contacts/{contact_id}` | Update an existing contact                           |
| `DELETE` | `/api/contacts/{contact_id}` | Delete an existing contact                           |

Contact endpoints use the authenticated user's PHP session to ensure users can only access their own contacts. Search supports partial matching across first name, last name, company, email, and phone number, along with configurable result limits and pagination.

For complete request schemas, response formats, validation errors, authentication requirements, and HTTP status codes, see the project's `openapi.yaml` specification or the SwaggerHub documentation linked above.

## AI Assistance Disclosure

This project was developed with assistance from generative AI tools:

- **Tool**: ChatGPT, GPT-5.6 Sol (OpenAI, chatgpt.com)
- **Dates**: 27 August 2026
- **Scope**: Repository setup, troubleshooting, and debugging
- **Use**: Helped configure the project repository and development tooling, troubleshoot setup and deployment issues, and diagnose technical errors

All AI-generated code was reviewed, tested, and modified to meet assignment requirements. Final implementation reflects my understanding of the concepts.
