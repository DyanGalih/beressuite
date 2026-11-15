# BeresSuite

**Free, Open-Source Invoicing Software for Freelancers, Startups & Small Businesses**

BeresSuite is a free, open-source invoicing application designed to help freelancers, startups, and small businesses manage their invoices, estimates, expenses, customers, and payments in one place.

Built upon the foundation of [Crater](https://github.com/crater-invoice-inc/crater), BeresSuite aims to modernize and improve the original application while preserving its simplicity and ease of use.

Our mission is to make invoicing and everyday financial administration accessible, reliable, and straightforward for growing businesses.

---

## Why BeresSuite?

- **Free & Open Source** — Manage your invoicing without mandatory subscription fees.
- **Simple Invoicing** — Create professional invoices and estimates.
- **Expense Tracking** — Keep track of business expenses.
- **Customer Management** — Organize customers and their billing information.
- **Payment Tracking** — Monitor paid and outstanding invoices.
- **Multiple Businesses** — Manage invoicing for more than one company.

---

## Technology Stack

- **Backend**: Laravel 12 (PHP 8.3+)
- **Frontend**: Vue 3 SPA with Vite & Tailwind CSS
- **Database**: MySQL / MariaDB (production), SQLite (testing & local dev)

---

## Getting Started

### Prerequisites

- PHP >= 8.3
- Composer
- Node.js & npm / yarn
- MySQL / MariaDB or SQLite

### Local Development Setup

1. **Clone the repository**:
   ```bash
   git clone https://github.com/DyanGalih/laravel-invoice.git
   cd laravel-invoice
   ```

2. **Install PHP dependencies**:
   ```bash
   composer install
   ```

3. **Install and build frontend assets**:
   ```bash
   npm install
   npm run build
   ```

4. **Environment setup**:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

5. **Run migrations and database seeds**:
   ```bash
   php artisan migrate --seed
   ```

6. **Serve the application**:
   ```bash
   php artisan serve
   ```

---

## Project Background & Attribution

BeresSuite is an independent modernization initiative based on the open-source Crater invoicing application.

We acknowledge and appreciate the original Crater maintainers and contributors whose work provides the foundation for this project:

- **Original Creator & Copyright**: © 2022 Crater Invoice, Inc.
- **Upstream Repository**: [crater-invoice-inc/crater](https://github.com/crater-invoice-inc/crater)
- **Special thanks to early contributors**:
  - [Birkhoff Lee](https://github.com/BirkhoffLee)
  - [Akaunting](https://github.com/akaunting/akaunting)
  - [MakerLab](https://github.com/MakerLab-Dev)
  - [Sebastian Cretu](https://github.com/sebastiancretu)
  - [Florian Gareis](https://github.com/TheZoker)

---

## License

BeresSuite is licensed under the **GNU Affero General Public License v3.0 (AGPL-3.0)**.  
See the [LICENSE](LICENSE) file for the full license text.
