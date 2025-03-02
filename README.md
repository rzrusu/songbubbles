![Preview](https://imgur.com/t25KxoV.png)


# SongBubbles

This is a fun little project I'm currently working on. It pulls live data about music albums from the Spotify API and showcases each track in a sort of bubble chart. Made with Laravel in the backend and Inertiajs's Vue adapter for the frontend. The bubble simulation is handled using d3.js

# Features
Here are some features so far
- Bubble chart visualization
    - Tracks are bigger or smaller depending on their popularity live
- Search for albums through the Spotify API
- Add or remove albums

# Planned Features
- Ability to log in with your Spotify account and see your personal most liked tracks
- Artist Connections Visualization
    - Select a range of different artists and see which have collaborated with eachother
- More optimization improvements
- More customization options for the way bubble cards look
- A way to share charts with friends through vanity links

# Installation

## Requirements
- PHP 8.2
- Composer
- Apache & MySQL I recommend [Laragon](https://laragon.org/) for ease of use, XAMPP also works.
- NodeJS & NPM
- Git

## Setup

Once cloned, run the following commands

```bash
# Install Composer dependencies
composer install

# Install npm dependencies
npm install

# Configure env file
# You need to set your database credentials and API keys here
cp .env.example .env

# Generate app key
php artisan key:generate

# Migrate tables
php artisan migrate

# Seed the database
php artisan db:seed
```

## Development Server

Start the development server on `http://localhost:8000`:

```bash
# npm
npm run dev

# Laravel server
php artisan serve

```

Open the application in your browser at http://127.0.0.1:8000.

Check out the [Laravel Docs](https://laravel.com/docs) or [Inertia.js Docs](https://inertiajs.com/) for more information.

## License

This project is licensed under the **Creative Commons Attribution-NonCommercial-ShareAlike 4.0 International (CC BY-NC-SA 4.0)**.

You are free to:
- **Share**: Copy and redistribute the material in any medium or format.
- **Adapt**: Remix, transform, and build upon the material.

Under the following terms:
- **Attribution**: You must give appropriate credit, provide a link to the license, and indicate if changes were made. You may do so in any reasonable manner, but not in any way that suggests the licensor endorses you or your use.
- **NonCommercial**: You may not use the material for commercial purposes.
- **ShareAlike**: If you remix, transform, or build upon the material, you must distribute your contributions under the same license as the original.

For the full license, visit [Creative Commons BY-NC-SA 4.0](https://creativecommons.org/licenses/by-nc-sa/4.0/).


<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>
