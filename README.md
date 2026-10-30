# Roost

Roost is a Laravel and MySQL vacation rental and host management project. This
starter provides account registration, sign in, and a dashboard for host and
guest accounts.

## Stack

- PHP 8.3 or newer
- Laravel 13
- MySQL
- Blade views with server-rendered HTML and CSS

Laravel is the main application and backend. The app uses Laravel's session
authentication and Eloquent models, so there is no separate API server or
JavaScript frontend framework to maintain.

## Run locally

Install PHP 8.3+, Composer, and MySQL. In the project folder:

1. Install the PHP dependencies:

~~~powershell
   composer install
~~~

2. Create a local environment file and application key:

~~~powershell
   Copy-Item .env.example .env
   php artisan key:generate
~~~

3. Create the MySQL database and set DB_USERNAME and DB_PASSWORD in .env for
   your local MySQL account:

~~~sql
   CREATE DATABASE roost CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
~~~

4. Create the tables and start the server:

~~~powershell
   php artisan migrate
   php artisan serve
~~~

5. Open http://127.0.0.1:8000/register and create a host or guest account.

New accounts have an empty dashboard until listing or booking records exist.
The app does not add demonstration users or passwords.

## MVC map

- Models: app/Models/User.php, Property.php, and Booking.php
- Views: resources/views/auth, resources/views/dashboard.blade.php, and
  resources/views/layouts
- Controllers: app/Http/Controllers/Auth and
  app/Http/Controllers/DashboardController.php
- Routes: routes/web.php
- Database: database/migrations

The sign-in and registration forms use Laravel validation, CSRF protection,
password hashing, session regeneration, and login throttling. Public
registration only permits host and guest roles. Staff and co-host permissions
are outside this login and dashboard starter.

The dashboard currently reads host listings, booking requests, arrivals, and
booking totals, or a guest's own reservations. Listing and booking creation
workflows are separate project features and are not part of this starter.
