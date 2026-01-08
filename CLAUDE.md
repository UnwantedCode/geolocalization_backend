# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a Symfony 6.4 backend API for a geolocation tracking application. The application allows users to create groups, track locations, share their location history with group members, and communicate via messages. It uses JWT authentication, PostgreSQL database, and includes Firebase Cloud Messaging integration for push notifications. An EasyAdmin panel provides administrative access.

## Development Commands

### Docker Environment

The project uses Docker with SSH tunneling to a remote PostgreSQL database:

```bash
# Start the Docker environment (includes SSH tunnel setup)
docker-compose up -d

# Stop the environment
docker-compose down

# Access the container shell
docker exec -it symfony-app bash
```

The Docker setup automatically creates an SSH tunnel from port 8543 (localhost) to the remote PostgreSQL server. The application connects to the database through this tunnel.

### Composer & Dependencies

```bash
# Install dependencies
composer install

# Update dependencies
composer update
```

### Database & Migrations

```bash
# Create a new migration
php bin/console make:migration

# Run migrations
php bin/console doctrine:migrations:migrate

# Check migration status
php bin/console doctrine:migrations:status
```

### Cache & Assets

```bash
# Clear cache
php bin/console cache:clear

# Install assets
php bin/console assets:install public

# Import asset maps
php bin/console importmap:install
```

### Testing

```bash
# Run all tests
php bin/phpunit

# Run specific test file
php bin/phpunit tests/YourTestFile.php
```

### Development Server

When not using Docker:

```bash
# Start the Symfony development server
symfony server:start

# Or use PHP built-in server
php -S localhost:8000 -t public/
```

## Architecture

### Authentication System

The application uses a dual authentication approach:

1. **JWT Authentication for API** (via LexikJWTAuthenticationBundle):
   - Public/private key pair stored in `config/jwt/`
   - Login endpoint: `/api/login` (accepts email/password)
   - Token refresh endpoint: `/api/token/refresh`
   - JWT tokens include custom payload: `email`, `id`, `username` (see `JWTCreatedListener`)
   - All `/api/*` routes (except login, register, token refresh) require valid JWT

2. **Session-based Authentication for Admin Panel**:
   - Form login at `/admin/login`
   - Requires `ROLE_ADMIN` for access to `/admin/*` routes
   - Uses standard Symfony security component with session storage

### Security Configuration

Security firewalls are defined in `config/packages/security.yaml`:

- `login`: Handles `/api/login` - JSON login with JWT response
- `register`: Open access to `/api/register`
- `firebase_register`: Open access to `/api/save-token` for Firebase token registration
- `admin_login`/`admin_logout`: Session-based admin authentication
- `admin`: Protected admin area requiring `ROLE_ADMIN`
- `api`: JWT-protected API routes
- `dev`: Bypasses security for Symfony dev tools

### Core Entities

**User** (`src/Entity/User.php`):
- Primary authentication entity (uses `email` as identifier)
- Many-to-many relationship with `Group`
- One-to-many with `LocationHistory` and `Message`
- Uses Gedmo Timestampable for automatic created/updated tracking
- Default role: `ROLE_USER`
- Stores avatar URL (default provided)

**Group** (`src/Entity/Group.php`):
- Auto-generates unique 6-digit join code
- Many-to-many bidirectional relationship with `User`
- One-to-many with `Message`
- Users can join groups via the code

**LocationHistory** (`src/Entity/LocationHistory.php`):
- Stores GPS coordinates (latitude/longitude)
- Tracks battery level at time of location update
- Belongs to a `User`
- Timestamped for historical tracking

**Message** (`src/Entity/Message.php`):
- Group chat messages
- Belongs to both `User` (sender) and `Group`

**DeviceToken** (`src/Entity/DeviceToken.php`):
- Stores Firebase Cloud Messaging tokens
- Used for push notifications

### API Platform Integration

The project uses API Platform 3.3 for automatic REST API generation:

- Entities marked with `#[ApiResource]` automatically get REST endpoints
- JSON-LD and JSON formats supported
- Pagination enabled by default (configurable via query params)
- API documentation available at `/api/docs`

Entities using API Platform:
- User: `/api/users`
- Group: `/api/groups`
- Both have search filters on `id` field (exact match)

### Custom API Endpoints

Located in `src/Controller/Api/`:

- **LoginController**: `/api/login` - Handles JWT authentication
- **RegistrationController**: `/api/register` - User registration with password hashing
- **JoinGroupController**: `/api/join-group` - Join a group using its code
- **UserGroupLocationController**: `/api/user-data` - Returns comprehensive user data including:
  - All users in the current user's groups
  - Each user's groups
  - Most recent location (as `locationCurrent`)
  - Location history (up to 20 most recent entries)
  - Battery levels for each location
- **FirebaseController**: `/api/save-token` - Save FCM device tokens for push notifications

### Admin Panel

Uses EasyAdminBundle with custom CRUD controllers in `src/Controller/Admin/`:

- Access at `/admin` (requires `ROLE_ADMIN`)
- **DashboardController**: Main admin dashboard
- **UserCrudController**: User management
- **GroupCrudController**: Group management
- **LocationHistoryCrudController**: Location history viewing
- **MessageCrudController**: Message management
- **FirebaseController**: Send push notifications to users/groups via Firebase Cloud Messaging
- **AdminSecurityController**: Admin login/logout handling

### Custom Repository Methods

**UserRepository** (`src/Repository/UserRepository.php`):
- `findGroupUsersWithLocations($currentUser)`: Returns all users who share at least one group with the current user, eagerly loading their groups and location histories. This is the core query for the `/api/user-data` endpoint.

### Event Listeners

Located in `src/EventListener/`:

- **JWTCreatedListener**: Adds custom data to JWT payload (email, id, username)
- **JWTDecodedListener**: Custom JWT validation logic

Event listeners are registered in `config/services.yaml` with kernel event tags.

### Data Transfer Objects (DTOs)

Simple DTOs in `src/Dto/` used for API responses:
- `UserDTO`: User data with groups and locations
- `GroupDTO`: Group data
- `LocationDTO`: Location data with timestamp and battery level

DTOs are manually constructed in controllers (not auto-mapped) for fine-grained control over API responses.

### Database Connection

PostgreSQL database accessed via SSH tunnel:
- Remote database on `pgsql1.small.pl:5432`
- SSH tunnel created through `s1.small.pl`
- Local connection on `localhost:8543`
- Doctrine ORM with migrations support

The SSH tunnel is automatically established in the Docker entrypoint. When running outside Docker, you'll need to create the tunnel manually:

```bash
ssh -f -N -L 8543:pgsql1.small.pl:5432 adamus1234@s1.small.pl
```

### Timestamps

The project uses `stof/doctrine-extensions-bundle` for automatic timestamp management. Entities that `use TimestampableEntity;` automatically get `createdAt` and `updatedAt` fields populated by Doctrine lifecycle events.

## Key Bundles

- **API Platform**: Automatic REST API generation
- **LexikJWTAuthenticationBundle**: JWT authentication
- **GesdinetJWTRefreshTokenBundle**: JWT refresh token handling
- **EasyAdminBundle**: Admin panel
- **NelmioCorsBundle**: CORS handling for API
- **StofDoctrineExtensionsBundle**: Doctrine extensions (timestamps, etc.)
- **KreaitFirebasePHP**: Firebase integration for push notifications
- **Doctrine ORM**: Database abstraction and entity management

## Important Notes

### Password Hashing

Uses Symfony's sodium hasher. New user passwords must be hashed before persisting:

```php
$hashedPassword = $passwordHasher->hashPassword($user, $plainPassword);
$user->setPassword($hashedPassword);
```

### Firebase Configuration

Firebase credentials are stored in `config/firebase/` directory. The Firebase SDK is used for sending push notifications through the admin panel.

### CORS Configuration

CORS is configured to allow localhost origins. For production deployment, update `CORS_ALLOW_ORIGIN` in `.env`.

### Migrations

All database schema changes should be done through migrations. Never modify the database schema directly. Migration files are in `migrations/` directory.

### API Stateless Configuration

Both API Platform and the API firewall are configured as stateless. This means:
- No sessions are used for API requests
- Each request must include a valid JWT token
- JWT tokens are stored client-side (not in server sessions)