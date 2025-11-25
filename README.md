<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

# Persona Application

Persona is a Laravel-based application designed to manage and enhance user experiences through AI-driven features. It integrates various services to provide a seamless and intelligent platform for managing personas, events, and communications.

## Features

- **Persona Management**: Create, update, and manage user personas.
- **Event Scheduling**: Organize and manage events with ease.
- **AI-Powered Insights**: Leverage AI for generating insights and recommendations.
- **Messaging System**: Communicate effectively with a built-in messaging system.
- **Memory Tags**: Attach and manage memory tags for better context and personalization.

## Technology Stack

- **Backend**: Laravel Framework
- **Frontend**: Vite.js for asset bundling
- **Database**: MySQL (or other supported databases)
- **AI Integration**: Cloudflare Workers AI, Gemini AI, ElevenLabs

## Installation

1. Clone the repository:
   ```bash
   git clone https://github.com/tohawk89/persona.git
   ```
2. Navigate to the project directory:
   ```bash
   cd persona
   ```
3. Install dependencies:
   ```bash
   composer install
   npm install
   ```
4. Set up the environment file:
   ```bash
   cp .env.example .env
   ```
   Update the `.env` file with your configuration.
5. Run migrations:
   ```bash
   php artisan migrate
   ```
6. Start the development server:
   ```bash
   php artisan serve
   ```

## Usage

- Access the application at `http://localhost:8000`.
- Use the admin panel to manage personas, events, and messages.

## Contributing

Contributions are welcome! Please follow the [contribution guidelines](https://laravel.com/docs/contributions).

## License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
