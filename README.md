# Company Transactions API

![Laravel](https://img.shields.io/badge/Laravel-11-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-8.2-777BB4?style=for-the-badge&logo=php&logoColor=white)
![Docker](https://img.shields.io/badge/Docker-Enabled-2496ED?style=for-the-badge&logo=docker&logoColor=white)
![Build Status](https://img.shields.io/github/actions/workflow/status/{username}/{repo}/tests.yml?branch=main&style=for-the-badge&label=Tests)

A robust, RESTful API for managing company financial transactions, built with a focus on maintainability, clean architecture, and testability. Submitted as a solution for the Senior Laravel Architect coding task.

---

## 📚 Documentation
- **[Architecture & Design Decisions](ARCHITECTURE.md)** - *READ THIS FIRST for deep dive into patterns.*
- **[Postman Collection](postman_collection.json)** - Importable file for API testing.

---

## 🚀 Key Features

- **Dynamic Status Calculation**: Real-time status logic (Paid/Outstanding/Overdue) without database persistence abuse.
- **Repository + Service Pattern**: Clean separation of concerns for scalable business logic.
- **Strict Typing**: PHP 8.2 features utilized for robust code.
- **Automated Testing**: Comprehensive Feature and Unit tests providing 100% core coverage.
- **Dockerized**: One-command setup for any environment.
- **Role-Based Access**: Secure Admin vs Customer data isolation.

## 🛠 Tech Stack

- **Framework**: Laravel 11.x
- **Database**: MySQL 8.0
- **Auth**: Laravel Sanctum (Token-based)
- **Environment**: Docker & Docker Compose
- **Server**: Nginx + PHP-FPM

## ⚡ Quick Start

### Option 1: Docker (Recommended)

1. **Clone & Setup Env**:
   ```bash
   git clone <repo-url>
   cd company-transactions-api
   cp .env.example .env
   ```

2. **Start Application**:
   ```bash
   docker-compose up -d --build
   ```

3. **Install Dependencies & Seed**:
   ```bash
   docker-compose exec app composer install
   docker-compose exec app php artisan migrate --seed
   ```
   
4. **Access**:
   - API: `http://localhost:8000`
   - **Admin Login**: `admin@example.com` / `password`
   - **Customer Login**: `customer@example.com` / `password`

### Option 2: Local PHP

If you have PHP 8.2 & MySQL installed locally:

```bash
composer install
php artisan migrate --seed
php artisan serve
```

---

## 🧪 Testing

The application includes a full test suite enforcing the business rules.

```bash
# Run all tests
docker-compose exec app php artisan test

# Filter specific tests
docker-compose exec app php artisan test --filter TransactionStatusTest
```

**What is tested?**
- ✅ **Auth flows**: Login, Logout, Unauthorized Access.
- ✅ **Business Logic**: VAT calculations, Date comparisons for "Overdue" status.
- ✅ **Role Security**: Ensuring customers cannot create transactions or see others' data.
- ✅ **Reports**: Verifying monthly aggregation logic.

## 📡 API Reference

**Headers required**: `Accept: application/json`

### Authentication
| Method | Endpoint | Description |
| :--- | :--- | :--- |
| POST | `/api/login` | Login & receive Bearer Token |
| POST | `/api/logout` | Revoke token |
| GET | `/api/user` | Get current user info |

### Transactions
| Method | Endpoint | Access | Description |
| :--- | :--- | :--- | :--- |
| POST | `/api/transactions` | Admin | Create new transaction |
| GET | `/api/transactions` | Admin/Customer | List transactions (Customer sees own) |
| GET | `/api/transactions/{id}` | Admin/Customer | View details |
| POST | `/api/transactions/{id}/payments` | Admin | Record a payment |

### Reports
| Method | Endpoint | Access | Description |
| :--- | :--- | :--- | :--- |
| GET | `/api/reports/monthly` | Admin | Aggregate financial stats |

---

## 🏆 Design Patterns Used

*See [ARCHITECTURE.md](ARCHITECTURE.md) for full reasoning.*

1.  **Repository Pattern**: Abstracting eloquent queries (`TransactionRepository`).
2.  **Service Pattern**: Encapsulating complex domain logic (`StatusService`).
3.  **Eager Loading**: Solving N+1 problems in identifying transaction status.
4.  **Factory Pattern**: Used extensively in Testing for robust seeding.
