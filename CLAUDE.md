#ProjectGuidelines.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Build, Lint, and Test Commands
- **Install Dependencies:** `composer install && npm install`
- **Build Frontend:** `npm run build` (production) or `npm run dev` (development with watch)
- **PHP Linting:** `composer run phpcs` (WordPress Coding Standards)
- **PHP Static Analysis:** `composer run phpstan` or `composer run psalm`
- **JavaScript Linting:** `npm run lint`
- **JavaScript Formatting:** `npm run format`
- **Run All Tests:** `composer run test` (PHP) and `npm run test` (JavaScript)
- **Run Single Test:** `composer run test -- --filter=TestName` (PHP) or `npm run test -- -t "test name"` (JavaScript)

## Code Style Guidelines
- **PHP:** WordPress Coding Standards, PHP 8.0+, typed properties/returns, proper sanitization/escaping
- **JavaScript:** ES6+, React Functional Components, Hooks, ESLint/Prettier rules
- **Naming:** `kebab-case` for files, `snake_case` for PHP functions, `camelCase` for JS functions, `PascalCase` for classes and React components
- **Imports:** Group by type (WP core, external libraries, internal modules) with blank line between groups
- **Error Handling:** Use try/catch with proper logging, validate all user inputs, follow WordPress security practices
- **CSS:** Tailwind CSS utility classes (primary) or BEM methodology using WordPress theme variables
- **Security:** Never hardcode API keys, sanitize inputs, escape outputs, use nonces for forms

## Project Specifics
- WordPress plugin structure with React frontend, shortcode deployment `[bookrec]`
- Black & White minimalist UI with smooth animations
- Caching strategy using WordPress Transients for API responses
- Use WordPress native APIs (HTTP, Settings, Options, Transients) whenever possible
