# IMS Backend

A RESTful JSON API backend for an **Inventory Management System**, built with **Laravel 10**. It supports three distinct user roles — Owner, Manager, and Worker — each with strictly scoped access to resources. Authentication is handled via **JWT tokens** stored in HttpOnly cookies.

---

## Table of Contents

1. [Tech Stack](#tech-stack)
2. [Project Structure](#project-structure)
3. [Getting Started](#getting-started)
4. [Environment Variables](#environment-variables)
5. [Database Schema](#database-schema)
6. [Authentication & Authorization](#authentication--authorization)
7. [API Overview](#api-overview)
   - [Auth Endpoints](#1-auth-endpoints-no-auth-required)
   - [Owner Endpoints](#2-owner-endpoints)
   - [Manager Endpoints](#3-manager-endpoints)
   - [Worker Endpoints](#4-worker-endpoints)
   - [Shared Endpoints](#5-shared-endpoints-all-roles)
8. [WebSocket Events](#websocket-events)
9. [Error Responses](#error-responses)
10. [Running Tests](#running-tests)
11. [Seeding the Database](#seeding-the-database)

---

## Tech Stack

| Layer | Technology |
|---|---|
| Framework | Laravel 10 (PHP ^8.1) |
| Authentication | `tymon/jwt-auth` ^2.3 — JWT stored in HttpOnly cookie |
| Real-time | Pusher via `pusher/pusher-php-server` ^7.2 |
| HTTP Client | `guzzlehttp/guzzle` ^7.2 |
| Testing | PHPUnit ^10.1 with SQLite in-memory |
| Dev Tools | Laravel Pint, Collision, Ignition |

---

## Project Structure

```
IMS_Backend/
├── ims-backend/                 # Laravel application root
│   ├── app/
│   │   ├── Events/              # WebSocket broadcast events
│   │   ├── Exceptions/
│   │   │   └── Handler.php      # Global JSON error responses
│   │   ├── Http/
│   │   │   ├── Controllers/
│   │   │   │   ├── AuthController.php
│   │   │   │   ├── OwnerController.php
│   │   │   │   ├── ManagerController.php
│   │   │   │   ├── WorkerController.php
│   │   │   │   └── SharedController.php
│   │   │   └── Middleware/
│   │   │       ├── ExtractTokenFromCookie.php   # Promotes JWT cookie → Bearer header
│   │   │       ├── EncryptCookies.php           # Excludes 'token' from encryption
│   │   │       ├── OwnerMiddleware.php          # Role guard: owner only
│   │   │       ├── ManagerMiddleware.php        # Role guard: manager only
│   │   │       └── WorkerMiddleware.php         # Role guard: worker only
│   │   └── Models/
│   │       ├── User.php
│   │       ├── Warehouse.php
│   │       ├── Product.php
│   │       ├── Order.php
│   │       ├── OrderItem.php
│   │       ├── PurchaseOrder.php
│   │       ├── PurchaseOrderItem.php
│   │       └── WorkerFlag.php
│   ├── database/
│   │   ├── migrations/          # 8 migration files
│   │   └── seeders/
│   │       └── OwnerSeeder.php  # Seeds the default owner account
│   ├── routes/
│   │   └── api.php              # All API route definitions
│   ├── tests/
│   │   └── Feature/
│   │       ├── AuthTest.php     # 17 tests
│   │       ├── OwnerTest.php    # 29 tests
│   │       ├── ManagerTest.php  # ~50 tests
│   │       ├── WorkerTest.php   # ~30 tests
│   │       └── SharedTest.php   # 14 tests
│   └── API_REFERENCE.md        # Detailed per-endpoint request/response docs
└── README.md
```

---

## Getting Started

### Prerequisites

- PHP ^8.1
- Composer
- A database (MySQL/PostgreSQL for production, SQLite for testing)
- A Pusher account (for WebSocket events)

### Installation

```bash
# 1. Clone the repository
git clone https://github.com/hendrix-llouchi/IMS_Backend.git
cd IMS_Backend/ims-backend

# 2. Install PHP dependencies
composer install

# 3. Copy and configure environment file
cp .env.example .env

# 4. Generate application key
php artisan key:generate

# 5. Generate JWT secret
php artisan jwt:secret

# 6. Run database migrations
php artisan migrate

# 7. Seed the default owner account
php artisan db:seed --class=OwnerSeeder

# 8. Start the development server
php artisan serve
```

The API will be available at `http://127.0.0.1:8000/api`.

---

## Environment Variables

Key variables to configure in your `.env` file:

```env
APP_NAME="IMS Backend"
APP_ENV=local
APP_URL=http://localhost

# Database (MySQL example)
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ims_db
DB_USERNAME=root
DB_PASSWORD=

# JWT (generated via php artisan jwt:secret)
JWT_SECRET=your-jwt-secret-here
JWT_TTL=60                     # Token lifetime in minutes

# Pusher (WebSocket broadcasting)
PUSHER_APP_ID=
PUSHER_APP_KEY=
PUSHER_APP_SECRET=
PUSHER_HOST=
PUSHER_PORT=443
PUSHER_SCHEME=https
PUSHER_APP_CLUSTER=mt1

BROADCAST_DRIVER=pusher
```

---

## Database Schema

### `users`
| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `name` | string | |
| `age` | integer | |
| `phone_number` | string | |
| `location` | string | |
| `emergency_contact` | string | |
| `email` | string unique | |
| `username` | string unique | |
| `password` | string | bcrypt hashed |
| `role` | enum | `owner`, `manager`, `worker` |
| `is_active` | boolean | default `true` |
| `is_temporary_password` | boolean | default `false` |
| `failed_attempts` | integer | default `0` |
| `locked_until` | timestamp nullable | Set after 3 failed logins |
| `reset_token` | string nullable | Password reset token |
| `reset_token_expires_at` | timestamp nullable | 60-minute expiry |

### `warehouses`
| Column | Type |
|---|---|
| `id` | bigint PK |
| `name` | string |
| `location` | string |

### `products`
| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `warehouse_id` | FK → warehouses | |
| `name` | string | |
| `type` | string | |
| `description` | string nullable | |
| `unit` | string | e.g. `kg`, `pcs` |
| `current_stock` | integer | |
| `max_stock_level` | integer | Used to calculate low-stock threshold |

### `orders`
| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `manager_id` | FK → users | Manager who created the order |
| `worker_id` | FK → users nullable | Assigned worker |
| `recipient_name` | string | |
| `recipient_contact` | string | |
| `delivery_deadline` | date | |
| `status` | enum | `unassigned`, `assigned`, `delivered`, `flagged` |
| `flag_reason` | string nullable | |

### `order_items`
| Column | Type |
|---|---|
| `id` | bigint PK |
| `order_id` | FK → orders |
| `product_id` | FK → products |
| `quantity` | integer |

### `purchase_orders`
| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `warehouse_id` | FK → warehouses | |
| `supplier_name` | string | |
| `status` | enum | `pending`, `complete`, `incomplete` |
| `expected_delivery_date` | date | |
| `actual_arrival_date` | date nullable | |

### `purchase_order_items`
| Column | Type |
|---|---|
| `id` | bigint PK |
| `purchase_order_id` | FK → purchase_orders |
| `product_id` | FK → products |
| `quantity_ordered` | integer |
| `quantity_received` | integer nullable |

### `worker_flags`
| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `manager_id` | FK → users | Manager who filed the flag |
| `worker_id` | FK → users | Worker being flagged |
| `reason` | string | |
| `status` | enum | `pending`, `dismissed`, `warning_issued` |
| `reviewed_at` | timestamp nullable | Set when owner acts on the flag |

---

## Authentication & Authorization

### How JWT Cookie Auth Works

```
Browser                         Server
  │                               │
  │── POST /api/auth/login ───────▶│ Validates credentials
  │                               │ Signs a JWT
  │◀── Set-Cookie: token=<JWT> ───│ HttpOnly cookie, not accessible via JS
  │                               │
  │── GET /api/owner/users ───────▶│
  │   Cookie: token=<JWT>         │ ExtractTokenFromCookie middleware
  │                               │   reads cookie → sets Authorization: Bearer
  │                               │ OwnerMiddleware validates JWT + role
  │◀── 200 OK ─────────────────── │
```

### Role Guards

| Route Prefix | Middleware | Who can access |
|---|---|---|
| `/api/auth/*` | _(none)_ | Public |
| `/api/owner/*` | `OwnerMiddleware` | `role = owner` only |
| `/api/manager/*` | `ManagerMiddleware` | `role = manager` only |
| `/api/worker/*` | `WorkerMiddleware` | `role = worker` only |
| `/api/shared/*` | `auth:api` | Any authenticated role |

### Account Security Rules

- **Failed login attempts** are tracked per user on the `failed_attempts` column.
- After **3 failed attempts**, the account is locked for **15 minutes** (`locked_until` is set). Response: `423`.
- **Temporary passwords** (`IMS@XXXX`) are issued when an account is created or when an owner resets a user's password. The `is_temporary_password` flag is `true` until the user changes it.
- **Password reset tokens** expire after **60 minutes**.
- Login is rate-limited to **3 attempts per 15 minutes** via the `throttle:3,15` middleware.

---

## API Overview

> **Base URL:** `http://127.0.0.1:8000/api`
> **Content-Type:** `application/json`
> **Auth:** All protected routes require the `token` HttpOnly cookie (set on login).
> **Pagination:** List endpoints return 20 records per page with the structure:
> ```json
> { "data": [...], "current_page": 1, "last_page": 3, "per_page": 20, "total": 55 }
> ```

---

### 1. Auth Endpoints (no auth required)

| Method | Endpoint | Description |
|---|---|---|
| `POST` | `/api/auth/login` | Login — sets the JWT cookie |
| `POST` | `/api/auth/logout` | Logout — clears the JWT cookie |
| `POST` | `/api/auth/change-password` | Change password (first-login flow) |
| `POST` | `/api/auth/forgot-password` | Request a password reset token |
| `POST` | `/api/auth/reset-password` | Reset password using a token |

**Login request:**
```json
{ "username": "owner", "password": "owner1234" }
```
**Login response `200`:**
```json
{ "message": "Login successful.", "role": "owner", "is_temporary_password": false }
```

---

### 2. Owner Endpoints

> Requires `role = owner`. Access by any other role → `403`.

#### User Management
| Method | Endpoint | Description |
|---|---|---|
| `POST` | `/api/owner/users/create` | Create a manager or worker account |
| `GET` | `/api/owner/users` | Paginated list of all non-owner users |
| `GET` | `/api/owner/users/{id}` | Get a single user |
| `PUT` | `/api/owner/users/{id}` | Update user profile fields |
| `PUT` | `/api/owner/users/{id}/deactivate` | Deactivate a user |
| `PUT` | `/api/owner/users/{id}/reactivate` | Reactivate a user |
| `PATCH` | `/api/owner/users/{id}/reset-password` | Generate a new temporary password |
| `DELETE` | `/api/owner/users/{id}` | Permanently delete a user |

#### Worker Flags
| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/api/owner/flags` | Paginated list of all **pending** flags |
| `PUT` | `/api/owner/flags/{id}/dismiss` | Dismiss a flag |
| `PUT` | `/api/owner/flags/{id}/warn` | Issue a warning against a worker |

#### Stock & Orders Oversight
| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/api/owner/stock` | Paginated product stock overview |
| `GET` | `/api/owner/orders` | Paginated order overview |

#### Reports & Settings
| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/api/owner/reports/financial` | Order count breakdown by status |
| `GET` | `/api/owner/reports/audit` | Products with `low_stock` flag and `stock_percentage` |
| `GET` | `/api/owner/settings` | Current system settings |
| `PUT` | `/api/owner/settings` | Update system settings |

---

### 3. Manager Endpoints

> Requires `role = manager`. Access by any other role → `403`.

#### User Management
| Method | Endpoint | Description |
|---|---|---|
| `POST` | `/api/manager/users/create` | Create a worker account |
| `GET` | `/api/manager/users` | Paginated list of all workers |
| `GET` | `/api/manager/workers/status` | Real-time worker availability snapshot |

#### Warehouses
| Method | Endpoint | Description |
|---|---|---|
| `POST` | `/api/manager/warehouses` | Create a warehouse |
| `GET` | `/api/manager/warehouses` | Paginated list of warehouses |
| `GET` | `/api/manager/warehouses/{id}` | Get a single warehouse |
| `PATCH` | `/api/manager/warehouses/{id}` | Update warehouse name/location |

#### Products
| Method | Endpoint | Description |
|---|---|---|
| `POST` | `/api/manager/products` | Create a product |
| `GET` | `/api/manager/products` | Paginated list of products |
| `GET` | `/api/manager/products/{id}` | Get a single product |
| `PATCH` | `/api/manager/products/{id}` | Update product fields |

#### Stock
| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/api/manager/stock` | Paginated stock overview |
| `PATCH` | `/api/manager/stock/{id}` 🔔 | Update stock level (broadcasts `LowStockAlert` if ≤30%) |
| `GET` | `/api/manager/stock/low` | List of products at or below 30% stock |

#### Orders
| Method | Endpoint | Description |
|---|---|---|
| `POST` | `/api/manager/orders` | Create a delivery order |
| `GET` | `/api/manager/orders` | Paginated list of orders |
| `GET` | `/api/manager/orders/{id}` | Get a single order |
| `PATCH` | `/api/manager/orders/{id}/assign` 🔔 | Assign order to a worker |
| `PATCH` | `/api/manager/orders/{id}/flag` | Flag an order |
| `PATCH` | `/api/manager/orders/{id}/resolve` | Resolve a flagged order |

#### Purchase Orders
| Method | Endpoint | Description |
|---|---|---|
| `POST` | `/api/manager/purchase-orders` | Create a purchase order |
| `GET` | `/api/manager/purchase-orders` | Paginated list of purchase orders |
| `GET` | `/api/manager/purchase-orders/{id}` | Get a single purchase order |
| `PATCH` | `/api/manager/purchase-orders/{id}/status` 🔔 | Mark complete/incomplete, update stock |

#### Worker Flags
| Method | Endpoint | Description |
|---|---|---|
| `POST` | `/api/manager/flags` | File a flag against a worker |
| `GET` | `/api/manager/flags` | Paginated list of all flags |

---

### 4. Worker Endpoints

> Requires `role = worker`. Workers can only view and act on their own assigned orders.

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/api/worker/orders` | Paginated read-only view of all orders |
| `GET` | `/api/worker/orders/assigned` | Paginated list of the worker's own assigned orders |
| `GET` | `/api/worker/orders/{id}` | Get a single order (must belong to the worker) |
| `PATCH` | `/api/worker/orders/{id}/deliver` 🔔 | Mark order delivered, deduct stock |
| `PATCH` | `/api/worker/orders/{id}/flag` 🔔 | Flag an order with a reason |
| `GET` | `/api/worker/stock` | Paginated read-only stock view |

---

### 5. Shared Endpoints (all roles)

> Accessible to any authenticated user (owner, manager, or worker). Read-only.

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/api/shared/warehouses` | Paginated list of all warehouses |
| `GET` | `/api/shared/warehouses/{id}` | Get a single warehouse (`404` if not found) |
| `GET` | `/api/shared/products` | Paginated list of all products |
| `GET` | `/api/shared/products/{id}` | Get a single product (`404` if not found) |

---

## WebSocket Events

Real-time events are broadcast via **Pusher**. Endpoints that trigger broadcasts are marked with 🔔 above.

| Event | Triggered By | Broadcast To |
|---|---|---|
| `OrderAssigned` | `PATCH /manager/orders/{id}/assign` | All other connected clients |
| `OrderStatusUpdated` | `PATCH /worker/orders/{id}/deliver` | All other connected clients |
| `OrderStatusUpdated` | `PATCH /worker/orders/{id}/flag` | All other connected clients |
| `LowStockAlert` | `PATCH /manager/stock/{id}` | All connected clients |
| `LowStockAlert` | `PATCH /manager/purchase-orders/{id}/status` | All connected clients |
| `LowStockAlert` | `PATCH /worker/orders/{id}/deliver` | All connected clients |
| `ShortDeliveryAlert` | `PATCH /manager/purchase-orders/{id}/status` (incomplete) | All connected clients |

---

## Error Responses

All errors are returned as JSON. The global exception handler in `app/Exceptions/Handler.php` normalises all responses:

| Status | Meaning |
|---|---|
| `400` | Bad request / invalid state transition (e.g. resolving a non-flagged order) |
| `401` | Unauthenticated — missing or invalid JWT cookie |
| `403` | Forbidden — wrong role, or account is deactivated |
| `404` | Resource not found |
| `405` | HTTP method not allowed |
| `422` | Validation failed — response body includes an `errors` object |
| `423` | Account temporarily locked after 3 failed login attempts |
| `429` | Too many requests — login rate limit exceeded |

**Example 422 response:**
```json
{
  "message": "Validation failed.",
  "errors": {
    "email": ["The email field is required."]
  }
}
```

---

## Running Tests

The test suite uses **PHPUnit** with an **SQLite in-memory database** for full isolation. Run from inside the `ims-backend/` directory.

```bash
# Run all tests
php artisan test

# Run a specific test file
php artisan test tests/Feature/AuthTest.php
php artisan test tests/Feature/OwnerTest.php
php artisan test tests/Feature/ManagerTest.php
php artisan test tests/Feature/WorkerTest.php
php artisan test tests/Feature/SharedTest.php

# Run with verbose output
php artisan test --verbose
```

### Test Coverage Summary

| Test File | Tests | What it covers |
|---|---|---|
| `AuthTest.php` | 17 | Login, logout, password change, forgot/reset password, lockout |
| `OwnerTest.php` | 29 | User CRUD, flags, stock/orders oversight, reports, settings, RBAC |
| `ManagerTest.php` | ~50 | Workers, warehouses, products, stock, orders, purchase orders, flags |
| `WorkerTest.php` | ~30 | Order listing, assigned orders, deliver/flag, stock view, RBAC |
| `SharedTest.php` | 14 | Warehouse/product read access for all roles, 401 for unauthenticated |

### Testing Notes

- All test classes use `RefreshDatabase` — every test starts with a clean database.
- The `OwnerSeeder` is run in `setUp()` to provide the default owner account.
- Authenticated requests use `withHeader('Authorization', "Bearer {$token}")` directly — required because `withCookie()` is intercepted by `EncryptCookies` middleware before the JWT can be read.
- `JWTAuth::fromUser($user)` is used to mint tokens programmatically without going through the login endpoint.

---

## Seeding the Database

The only seeder provided is `OwnerSeeder`, which creates the default system owner account:

```bash
php artisan db:seed --class=OwnerSeeder
```

**Default owner credentials:**

| Field | Value |
|---|---|
| Username | `owner` |
| Password | `owner1234` |
| Email | `owner@ims.com` |
| Role | `owner` |

> ⚠️ Change the owner password immediately after first login in any production environment.

---

## Full API Reference

For detailed per-endpoint request/response documentation including all field validations and example payloads, see [`ims-backend/API_REFERENCE.md`](./ims-backend/API_REFERENCE.md).