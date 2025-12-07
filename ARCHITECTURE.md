# Architecture & Design Decisions

## Overview
This document outlines the architectural choices made for the Company Transactions API. The goal was to build a scalable, maintainable, and testable application following standard Laravel best practices while addressing specific business questions around financial status calculation.

## 🏗 layers & Patterns

### 1. Repository Pattern
**Location**: `app/Repositories`

I chose to implement the Repository pattern to decouple the business logic from the data access layer.
- **Why?**: It allows us to swap out the underlying data source if needed (unlikely in this scope, but good practice) and, more importantly, provides a central place for complex queries (like `queryForUser`).
- **Implementation**:
    - `TransactionRepository`: Handles creation and retrieval of transactions.
    - `PaymentRepository`: Handles creation of payments.
- **Usage**: Controllers and Services interact with Repositories, never directly with Eloquent models for write operations (mostly).

### 2. Service Layer
**Location**: `app/Services`

Business logic is encapsulated in Services to keep Controllers "skinny."
- **StatusService**:
    - **Problem**: Transaction status (`paid`, `outstanding`, `overdue`) is computed on-the-fly and depends on time and payment history.
    - **Solution**: A dedicated service handles this logic. It calculates VAT-inclusive totals and compares payments against due dates.
    - **Optimization**: To prevent N+1 query performance issues when listing transactions, the service logic was refactored to operate on eager-loaded collections (`$transaction->payments`) rather than executing database queries for each row.
- **TransactionService**:
    - Orchestrates high-level operations like creating a transaction or adding a payment (which might involve multiple repository calls or DB transactions).

### 3. Controller Responsibility
**Location**: `app/Http/Controllers`

Controllers are responsible strictly for:
- Validating generic HTTP inputs.
- Authorization (checking logic/roles).
- Delegating work to Services/Repositories.
- Formatting the JSON response.

### 4. Authentication & Authorization
- **Auth**: Laravel Sanctum (Token-based).
- **Authorization**: Role-based checks (Admin vs Customer) implemented in Controllers.
- **Security Check**: We ensure that Customers can only view their *own* transactions via query filtering in the Repository and explicit checks in the `show` method.

## 💡 Key Technical Decisions

### Handling "Computed" Status
The requirement stated that status (Paid/Outstanding/Overdue) is **not stored** in the database.
- **Trade-off**: calculating on read is slower than reading a stored column, but it guarantees accuracy relative to the *current time* (e.g., a transaction becomes "overdue" automatically at midnight without a background job).
- **Mitigation**: We optimized the list endpoint to eager-load payments (`::with('payments')`), ensuring that calculating status for 50 transactions doesn't result in 51+ queries.

### Monthly Reports
The monthly report gathers data into buckets (Month/Year).
- **Approach**: We fetch transactions within the date range and aggregate them in memory (PHP).
- **Why PHP aggregation?**:
    - The logic for "status at that time" versus "current status" can be complex in SQL.
    - Iterating through a filtered result set allows us to reuse the robust domain logic in `StatusService`.
    - **Scalability Note**: For massive datasets (millions of rows), this should be moved to a raw SQL aggregation or a materialized view. For typical usage, the PHP approach is clean and maintainable.

### Docker Environment
The application is fully containerized using:
- **PHP 8.2 FPM**
- **Nginx** (Serving the API)
- **MySQL 8.0**
- **Docker Compose** for orchestration.
This ensures the environment is identical across development and production.

## ✅ Testing Strategy

A comprehensive test suite was implemented using PHPUnit/Pest.
- **Unit Tests (`TransactionStatusTest`)**:
    - Verifies the core business logic: VAT, status rules, and date comparisons.
    - Uses an in-memory SQLite database for speed.
- **Feature Tests (`TransactionFlowTest`)**:
    - Tests the API from the outside-in (HTTP requests).
    - Verifies Auth, Role restrictions, Input validation, and JSON structure.
    - Ensures that the end-to-end user flows work as expected.
