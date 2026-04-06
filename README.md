# 📡 Skynet Saleskit

Skynet Saleskit is a field service and lead management application built with **Laravel 13** and **Filament v5**. It enables sales teams to track potential customers, categorize interest, and assign installation or disconnection tasks to technicians.

## 🚀 Features

- **👤 User Management (RBAC)**
  - **Admin**: Full access to all resources and user management.
  - **Technician**: Access restricted to assigned tasks and customer information during site visits.
- **🗺️ Customer Lead Tracking**
  - Advanced form with cascading selects for Indonesian administrative areas (Province → City → District → Village).
  - Integrated **Map Picker** for precise GPS coordinate capture.
  - **Interest Logic**: Track why prospects are not converting (e.g., "Tidak Tercover") with conditional reason fields.
- **🛠️ Task Management**
  - Assign Installation or Disconnection tasks to specific technicians.
  - Automated status tracking (Waiting, In Progress, Completed, Failed).
  - Photo evidence and technician notes for site verification.
- **📁 Production Ready**
  - Pre-configured `nixpacks.toml` and `docker/nginx.conf` for **Coolify** deployment.
  - Specialized `deploy.sh` for automated migrations and cache optimization.

## 🛠️ Technical Stack

- **Framework**: Laravel 13
- **Admin Panel**: Filament v5 (using custom Schemas/Tables architecture)
- **Database**: MySQL/MariaDB
- **Administrative Areas**: `laravolt/indonesia`
- **Map Integration**: `dotswan/filament-map-picker` (Leaflet-based)
- **UI Components**: Tailwind CSS & Blade Icons

## ⚙️ Installation

### Local Development
1. Clone the repository:
   ```bash
   git clone https://github.com/fairusinampratama/skynet-saleskit.git
   cd skynet-saleskit
   ```
2. Install dependencies:
   ```bash
   composer install
   npm install && npm run build
   ```
3. Setup environment:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
4. Run migrations and seed data (includes Indonesian regional data):
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

## 📸 Photo Evidence
The system supports photo evidence for both customers and tasks. By default, it uses the `public` disk and includes a built-in browser-based image editor for cropping and resizing before upload.

## 📄 License
This project is private and intended for internal use at Skynet.
