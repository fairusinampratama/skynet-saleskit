# Skynet Saleskit

Skynet Saleskit is a field registration application built with **Laravel 13** and **Filament v5**. It enables technicians to submit customer registrations with KTP photos, GPS coordinates, and installation details for admin review.

## Features

- **👤 User Management (RBAC)**
  - **Admin**: Full access to all resources and user management.
  - **Technician**: Access restricted to mobile customer registration workflows.
- **🗺️ Customer Lead Tracking**
  - Technician registration with SalesKit area selection, KTP capture, GPS coordinates, and installation address.
  - Integrated **Map Picker** for precise GPS coordinate capture.
  - **Interest Logic**: Track why prospects are not converting (e.g., "Tidak Tercover") with conditional reason fields.
- **📁 Production Ready**
  - Pre-configured `nixpacks.toml` and `docker/nginx.conf` for **Coolify** deployment.
  - Specialized `deploy.sh` for automated migrations and cache optimization.

## Technical Stack

- **Framework**: Laravel 13
- **Admin Panel**: Filament v5 (using custom Schemas/Tables architecture)
- **Database**: MySQL/MariaDB
- **Map Integration**: `dotswan/filament-map-picker` (Leaflet-based)
- **UI Components**: Tailwind CSS & Blade Icons

## Installation

### Local Development
1. Clone the repository:
   ```bash
   git clone https://github.com/fairusinampratama/skynet-saleskit.git
   cd skynet-saleskit
   ```
2. Install dependencies:
   ```bash
   nvm use
   composer install
   npm ci
   npm run build
   ```
   Frontend builds require Node 20.19+ or 22.12+ because this project uses Vite 8 and Tailwind CSS 4. The repo pins local development to Node 24 through `.nvmrc` and `.node-version`; Node 18 will not build the frontend.

   If the host Node/npm install is unavailable or outdated, verify the frontend build through the local Docker app image instead:
   ```bash
   docker compose run --rm --no-deps app bash -lc "npm ci && npm run build"
   ```
3. Setup environment:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
4. Run migrations and seed data:
   ```bash
   php artisan migrate --seed
   ```
5. Link storage:
   ```bash
   php artisan storage:link
   ```

### Production Deployment (Coolify)
This project is optimized for deployment via **Coolify** using Nixpacks:
1. Connect your repository to Coolify.
2. The `nixpacks.toml` will automatically detect the build environment.
3. Set your production environment variables in the Coolify dashboard.
4. The `deploy.sh` script handles migrations and optimizations automatically on every push.

## Photo Evidence
The system supports KTP photos and optional location photos for registrations. By default, it uses the `public` disk and includes browser-side image resizing before upload.

## Data Handling

Do not commit real KTP images, installation photos, customer addresses, phone numbers, GPS coordinates, production `.env` files, or exported registration data. Use anonymized fixtures for tests and portfolio demonstrations.

## Portfolio Notes

This repository is presented as a Laravel/Filament operations project focused on field data capture, role-based access, geolocation input, image handling, and deployment-ready application structure.
