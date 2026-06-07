# IMS Backend — API Reference

> **Base URL:** `/api`
> **Auth:** JWT token stored in an `HttpOnly` cookie (`token`). All protected routes require the cookie to be present.
> **Pagination:** All list endpoints return a standard Laravel paginator object: `{ data, current_page, last_page, per_page, total, ... }` with **20 records per page** by default.
> **WebSocket Events:** Endpoints that broadcast real-time events are marked with 🔔.

---

## 1. Authentication

> No authentication required unless noted. Login is rate-limited to **3 attempts per 15 minutes**.

---

### `POST /api/auth/login`

Authenticates a user and sets a `token` HttpOnly cookie on success. Locks the account for 15 minutes after 3 failed attempts.

**Request Body:**
```json
{ "username": "string", "password": "string" }
```

**Response `200`:**
```json
{ "message": "Login successful.", "role": "owner|manager|worker", "is_temporary_password": false }
```

---

### `POST /api/auth/logout`

Clears the `token` cookie and ends the session.

**Request Body:** None

**Response `200`:**
```json
{ "message": "Logged out successfully." }
```

---

### `POST /api/auth/change-password`

Changes a user's password. Intended for first-login temporary password replacement.

**Request Body:**
```json
{ "username": "string", "new_password": "string (min: 6)" }
```

**Response `200`:**
```json
{ "message": "Password changed successfully. Please log in with your new password." }
```

---

### `POST /api/auth/forgot-password`

Generates a password reset token for the given email. Always returns a generic message to avoid email enumeration.

**Request Body:**
```json
{ "email": "string (valid email)" }
```

**Response `200`:**
```json
{ "message": "If this email exists in our system you will receive a password reset link shortly." }
```

---

### `POST /api/auth/reset-password`

Resets the user's password using a valid reset token (expires after 60 minutes).

**Request Body:**
```json
{ "token": "string", "new_password": "string (min: 6)" }
```

**Response `200`:**
```json
{ "message": "Password reset successful. Please log in with your new password." }
```

---

## 2. Owner

> Requires `owner` role middleware. All requests must carry the JWT cookie.

---

### User Management

---

### `POST /api/owner/users/create`

Creates a new manager or worker account and generates a temporary password (`IMS@XXXX`).

**Request Body:**
```json
{
  "name": "string",
  "age": "integer",
  "phone_number": "string",
  "location": "string",
  "emergency_contact": "string",
  "email": "string (unique)",
  "username": "string (unique)",
  "role": "manager|worker"
}
```

**Response `201`:**
```json
{ "message": "User created successfully.", "user": { ... }, "temporary_password": "IMS@XXXX" }
```

---

### `GET /api/owner/users`

Returns a paginated list of all non-owner users (managers and workers).

**Request Body:** None

**Response `200`:** Paginated list of users with fields: `id, name, age, phone_number, location, email, username, role, is_active`.

---

### `GET /api/owner/users/{id}`

Returns full details for a single non-owner user.

**Request Body:** None

**Response `200`:**
```json
{ "user": { "id", "name", "age", "phone_number", "location", "emergency_contact", "email", "username", "role", "is_active" } }
```

---

### `PUT /api/owner/users/{id}`

Updates profile fields for a non-owner user. All fields are optional.

**Request Body (all optional):**
```json
{ "name": "string", "age": "integer", "phone_number": "string", "location": "string", "emergency_contact": "string", "email": "string" }
```

**Response `200`:**
```json
{ "message": "User updated successfully.", "user": { ... } }
```

---

### `PUT /api/owner/users/{id}/deactivate`

Deactivates a user account, preventing login. Returns `400` if already deactivated.

**Request Body:** None

**Response `200`:**
```json
{ "message": "User deactivated successfully." }
```

---

### `PUT /api/owner/users/{id}/reactivate`

Reactivates a deactivated user account and clears lockout state. Returns `400` if already active.

**Request Body:** None

**Response `200`:**
```json
{ "message": "User reactivated successfully." }
```

---

### `PATCH /api/owner/users/{id}/reset-password`

Generates and sets a new temporary password for the user, forcing a password change on next login.

**Request Body:** None

**Response `200`:**
```json
{ "message": "Password reset successfully.", "temporary_password": "IMS@XXXX" }
```

---

### `DELETE /api/owner/users/{id}`

Permanently deletes a non-owner user record.

**Request Body:** None

**Response `200`:**
```json
{ "message": "User permanently removed." }
```

---

### Flags

---

### `GET /api/owner/flags`

Returns a paginated list of all **pending** worker flags with associated worker and manager details.

**Request Body:** None

**Response `200`:** Paginated list of `WorkerFlag` objects with `worker` and `manager` relations.

---

### `PUT /api/owner/flags/{id}/dismiss`

Marks a pending flag as dismissed. Returns `400` if the flag was already reviewed.

**Request Body:** None

**Response `200`:**
```json
{ "message": "Flag dismissed successfully." }
```

---

### `PUT /api/owner/flags/{id}/warn`

Marks a pending flag as `warning_issued`. Returns `400` if the flag was already reviewed.

**Request Body:** None

**Response `200`:**
```json
{ "message": "Warning issued successfully." }
```

---

### Stock & Orders Oversight

---

### `GET /api/owner/stock`

Returns a paginated view of all products with their warehouse information.

**Request Body:** None

**Response `200`:** Paginated list of products with `warehouse` relation.

---

### `GET /api/owner/orders`

Returns a paginated view of all orders with associated worker, manager, and product items.

**Request Body:** None

**Response `200`:** Paginated list of orders with `worker`, `manager`, and `items.product` relations.

---

### Reports

---

### `GET /api/owner/reports/financial`

Returns order count breakdown by status (total, delivered, flagged, pending).

**Request Body:** None

**Response `200`:**
```json
{ "total_orders": 0, "delivered_orders": 0, "flagged_orders": 0, "pending_orders": 0 }
```

---

### `GET /api/owner/reports/audit`

Returns all products with computed `low_stock` flag and `stock_percentage` relative to max stock level.

**Request Body:** None

**Response `200`:**
```json
{ "audit": [ { "id", "name", "type", "unit", "current_stock", "max_stock_level", "low_stock": true|false, "stock_percentage": 45.0, "warehouse": { ... } } ] }
```

---

### Settings

---

### `GET /api/owner/settings`

Returns current system configuration values (thresholds, lockout rules, pagination size).

**Request Body:** None

**Response `200`:**
```json
{ "settings": { "low_stock_threshold": 30, "pagination_per_page": 20, "lockout_attempts": 3, "lockout_duration": 15, "reset_token_expiry": 60 } }
```

---

### `PUT /api/owner/settings`

Updates system settings. (Implementation stubbed — accepts any body.)

**Request Body:** Settings fields to update.

**Response `200`:**
```json
{ "message": "Settings updated successfully." }
```

---

## 3. Manager

> Requires `manager` role middleware.

---

### User Management

---

### `POST /api/manager/users/create`

Creates a new worker account under the manager's scope with a temporary password.

**Request Body:**
```json
{
  "name": "string",
  "age": "integer",
  "phone_number": "string",
  "location": "string",
  "emergency_contact": "string",
  "email": "string (unique)",
  "username": "string (unique)"
}
```

**Response `201`:**
```json
{ "message": "Worker created successfully.", "user": { ... }, "temporary_password": "IMS@XXXX" }
```

---

### `GET /api/manager/users`

Returns a paginated list of all workers with basic profile fields.

**Request Body:** None

**Response `200`:** Paginated list of workers with fields: `id, name, age, phone_number, location, email, username, is_active`.

---

### `GET /api/manager/workers/status`

Returns a real-time availability snapshot of all active workers (`Available` or `Busy`).

**Request Body:** None

**Response `200`:**
```json
{ "workers": [ { "id": 1, "name": "Alice", "status": "Available" } ] }
```

---

### Warehouses

---

### `POST /api/manager/warehouses`

Creates a new warehouse.

**Request Body:**
```json
{ "name": "string", "location": "string" }
```

**Response `201`:**
```json
{ "message": "Warehouse created successfully.", "warehouse": { ... } }
```

---

### `GET /api/manager/warehouses`

Returns a paginated list of all warehouses.

**Request Body:** None

**Response `200`:** Paginated list of warehouse records.

---

### `GET /api/manager/warehouses/{id}`

Returns details for a single warehouse.

**Request Body:** None

**Response `200`:**
```json
{ "warehouse": { "id", "name", "location", ... } }
```

---

### `PATCH /api/manager/warehouses/{id}`

Partially updates a warehouse's name or location.

**Request Body (all optional):**
```json
{ "name": "string", "location": "string" }
```

**Response `200`:**
```json
{ "message": "Warehouse updated successfully.", "warehouse": { ... } }
```

---

### Products

---

### `POST /api/manager/products`

Creates a new product and assigns it to a warehouse with initial stock information.

**Request Body:**
```json
{
  "warehouse_id": "integer (exists)",
  "name": "string",
  "type": "string",
  "description": "string (nullable)",
  "unit": "string",
  "current_stock": "integer (min: 0)",
  "max_stock_level": "integer (min: 1)"
}
```

**Response `201`:**
```json
{ "message": "Product created successfully.", "product": { ... } }
```

---

### `GET /api/manager/products`

Returns a paginated list of all products with their warehouse.

**Request Body:** None

**Response `200`:** Paginated list of products with `warehouse` relation.

---

### `GET /api/manager/products/{id}`

Returns full details for a single product including its warehouse.

**Request Body:** None

**Response `200`:**
```json
{ "product": { "id", "name", "type", "unit", "current_stock", "max_stock_level", "warehouse": { ... } } }
```

---

### `PATCH /api/manager/products/{id}`

Partially updates a product's fields. All fields are optional.

**Request Body (all optional):**
```json
{ "name": "string", "type": "string", "description": "string", "unit": "string", "current_stock": "integer", "max_stock_level": "integer" }
```

**Response `200`:**
```json
{ "message": "Product updated successfully.", "product": { ... } }
```

---

### Stock

---

### `GET /api/manager/stock`

Returns a paginated list of all products with current stock levels and warehouse info.

**Request Body:** None

**Response `200`:** Paginated list of products with `warehouse` relation.

---

### `PATCH /api/manager/stock/{id}` 🔔

Updates the stock level for a product. Broadcasts a **`LowStockAlert`** WebSocket event if the new level falls at or below 30% of `max_stock_level`.

**Request Body:**
```json
{ "current_stock": "integer (min: 0)" }
```

**Response `200`:**
```json
{ "message": "Stock updated successfully.", "product": { ... } }
```

---

### `GET /api/manager/stock/low`

Returns all products whose current stock is at or below 30% of their maximum stock level.

**Request Body:** None

**Response `200`:**
```json
{ "low_stock_products": [ { ... } ] }
```

---

### Orders

---

### `POST /api/manager/orders`

Creates a new delivery order with one or more product line items. Initial status is `unassigned`.

**Request Body:**
```json
{
  "recipient_name": "string",
  "recipient_contact": "string",
  "delivery_deadline": "date",
  "items": [ { "product_id": "integer", "quantity": "integer (min: 1)" } ]
}
```

**Response `201`:**
```json
{ "message": "Order created successfully.", "order": { ..., "items": [ ... ] } }
```

---

### `GET /api/manager/orders`

Returns a paginated list of all orders with worker and product item details.

**Request Body:** None

**Response `200`:** Paginated list of orders with `worker` and `items.product` relations.

---

### `GET /api/manager/orders/{id}`

Returns details for a single order including its assigned worker and items.

**Request Body:** None

**Response `200`:**
```json
{ "order": { ..., "worker": { ... }, "items": [ ... ] } }
```

---

### `PATCH /api/manager/orders/{id}/assign` 🔔

Assigns an `unassigned` order to a worker and broadcasts an **`OrderAssigned`** WebSocket event to all other clients.

**Request Body:**
```json
{ "worker_id": "integer (exists, role=worker)" }
```

**Response `200`:**
```json
{ "message": "Order assigned successfully.", "order": { ... } }
```

---

### `PATCH /api/manager/orders/{id}/flag`

Flags an order with a reason, setting its status to `flagged`.

**Request Body:**
```json
{ "flag_reason": "string" }
```

**Response `200`:**
```json
{ "message": "Order flagged successfully.", "order": { ... } }
```

---

### `PATCH /api/manager/orders/{id}/resolve`

Resolves a `flagged` order, reverting its status back to `assigned`. Returns `400` if order is not flagged.

**Request Body:** None

**Response `200`:**
```json
{ "message": "Order resolved successfully.", "order": { ... } }
```

---

### Purchase Orders

---

### `POST /api/manager/purchase-orders`

Creates a new purchase order from a supplier for restocking, linked to a warehouse.

**Request Body:**
```json
{
  "warehouse_id": "integer (exists)",
  "supplier_name": "string",
  "expected_delivery_date": "date",
  "items": [ { "product_id": "integer", "quantity_ordered": "integer (min: 1)" } ]
}
```

**Response `201`:**
```json
{ "message": "Purchase order created successfully.", "purchase_order": { ..., "items": [ ... ] } }
```

---

### `GET /api/manager/purchase-orders`

Returns a paginated list of all purchase orders with warehouse and product item details.

**Request Body:** None

**Response `200`:** Paginated list of purchase orders with `warehouse` and `items.product` relations.

---

### `GET /api/manager/purchase-orders/{id}`

Returns full details for a single purchase order.

**Request Body:** None

**Response `200`:**
```json
{ "purchase_order": { ..., "warehouse": { ... }, "items": [ ... ] } }
```

---

### `PATCH /api/manager/purchase-orders/{id}/status` 🔔

Marks a pending purchase order as `complete` or `incomplete`, records received quantities, and increments product stock. Broadcasts a **`ShortDeliveryAlert`** if status is `incomplete`. Broadcasts a **`LowStockAlert`** for any product that remains below threshold after the update.

**Request Body:**
```json
{
  "status": "complete|incomplete",
  "actual_arrival_date": "date",
  "items": [
    { "purchase_order_item_id": "integer", "quantity_received": "integer (min: 0)" }
  ]
}
```

**Response `200`:**
```json
{ "message": "Purchase order updated successfully.", "purchase_order": { ..., "items": [ ... ] } }
```

---

### Worker Flags

---

### `POST /api/manager/flags`

Files a flag report against a worker with a stated reason.

**Request Body:**
```json
{ "worker_id": "integer (exists, role=worker)", "reason": "string" }
```

**Response `201`:**
```json
{ "message": "Worker flagged successfully.", "flag": { ... } }
```

---

### `GET /api/manager/flags`

Returns a paginated list of all worker flags (all statuses) with worker and manager details.

**Request Body:** None

**Response `200`:** Paginated list of `WorkerFlag` records with `worker` and `manager` relations.

---

## 4. Worker

> Requires `worker` role middleware. Workers can only access their own assigned orders.

---

### `GET /api/worker/orders`

Returns a paginated read-only view of all orders in the system with product and unit details.

**Request Body:** None

**Response `200`:** Paginated list of orders with `worker` and `items.product` (name, unit) relations.

---

### `GET /api/worker/orders/assigned`

Returns a paginated list of orders assigned specifically to the authenticated worker.

**Request Body:** None

**Response `200`:** Paginated list of the worker's assigned orders with `items.product` details.

---

### `GET /api/worker/orders/{id}`

Returns details for a single order that belongs to the authenticated worker. Returns `404` if order exists but belongs to another worker.

**Request Body:** None

**Response `200`:**
```json
{ "order": { ..., "items": [ { "product": { "name", "unit" }, "quantity" } ] } }
```

---

### `PATCH /api/worker/orders/{id}/deliver` 🔔

Marks the worker's assigned order as `delivered`, deducts item quantities from product stock, and broadcasts an **`OrderStatusUpdated`** WebSocket event to managers. Also broadcasts **`LowStockAlert`** for any product that falls below the 30% threshold after deduction.

**Request Body:** None

**Response `200`:**
```json
{ "message": "Order marked as delivered successfully.", "order": { ... } }
```

---

### `PATCH /api/worker/orders/{id}/flag` 🔔

Flags the worker's assigned order as problematic with a reason and broadcasts an **`OrderStatusUpdated`** WebSocket event to managers.

**Request Body:**
```json
{ "flag_reason": "string" }
```

**Response `200`:**
```json
{ "message": "Order flagged successfully.", "order": { ... } }
```

---

### `GET /api/worker/stock`

Returns a paginated read-only view of all products and their stock levels with warehouse info.

**Request Body:** None

**Response `200`:** Paginated list of products with `warehouse` relation.

---

## 5. Shared

> Available to all authenticated users (owner, manager, worker). Requires a valid JWT cookie.

---

### `GET /api/shared/warehouses`

Returns a paginated list of all warehouses.

**Request Body:** None

**Response `200`:** Paginated list of warehouse records.

---

### `GET /api/shared/warehouses/{id}`

Returns details for a single warehouse. Returns `404` if not found.

**Request Body:** None

**Response `200`:**
```json
{ "warehouse": { "id", "name", "location", ... } }
```

---

### `GET /api/shared/products`

Returns a paginated list of all products with their associated warehouse.

**Request Body:** None

**Response `200`:** Paginated list of products with `warehouse` relation.

---

### `GET /api/shared/products/{id}`

Returns details for a single product including its warehouse. Returns `404` if not found.

**Request Body:** None

**Response `200`:**
```json
{ "product": { "id", "name", "type", "unit", "current_stock", "max_stock_level", "warehouse": { ... } } }
```

---

## WebSocket Events Summary

| Event | Trigger Endpoint | Broadcast To |
|---|---|---|
| `OrderAssigned` | `PATCH /manager/orders/{id}/assign` | All other clients |
| `OrderStatusUpdated` | `PATCH /worker/orders/{id}/deliver` | All other clients |
| `OrderStatusUpdated` | `PATCH /worker/orders/{id}/flag` | All other clients |
| `LowStockAlert` | `PATCH /manager/stock/{id}` | All clients |
| `LowStockAlert` | `PATCH /manager/purchase-orders/{id}/status` | All clients |
| `LowStockAlert` | `PATCH /worker/orders/{id}/deliver` | All clients |
| `ShortDeliveryAlert` | `PATCH /manager/purchase-orders/{id}/status` (incomplete) | All clients |

---

## Error Responses

| Status | Meaning |
|---|---|
| `400` | Bad request / invalid state transition |
| `401` | Unauthenticated (missing or invalid JWT) |
| `403` | Forbidden (account deactivated) |
| `404` | Resource not found |
| `405` | HTTP method not allowed |
| `422` | Validation failed — body includes `errors` object |
| `423` | Account temporarily locked |
| `429` | Too many requests — rate limit exceeded |
