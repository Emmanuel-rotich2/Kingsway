# Uniform Sales Module - Complete Implementation Guide

**Kingsway Academy Management System**

---

## Overview

The Uniform Sales Module adds comprehensive uniform inventory and sales tracking to the Kingsway Academy Management System. It manages all school uniform items, tracks student purchases, monitors payment status, and provides detailed sales analytics.

### Uniform Items Tracked
1. **Sweater** - School grey sweater (multiple sizes)
2. **Socks** - White cotton socks (pack of 3)
3. **Shorts** - Navy blue shorts (multiple sizes)
4. **Trousers** - Navy blue trousers (multiple sizes)
5. **Shirts** - White shirts for boys (multiple sizes)
6. **Blouses** - White blouses for girls (multiple sizes)
7. **Skirts** - Navy blue skirts for girls (multiple sizes)
8. **Games Skirt** - PE games skirt (multiple sizes)
9. **Sleeping Pajamas** - Sleeping pajama sets (multiple sizes)

### Sizes Supported
- XS (Extra Small)
- S (Small)
- M (Medium)
- L (Large)
- XL (Extra Large)
- XXL (Extra Extra Large)

---

## Database Schema

### Core Tables

#### 1. `inventory_categories` (Updated)
New category added for uniforms:
- **ID:** 10
- **Name:** Uniforms
- **Code:** UNIF
- **Description:** School uniforms (sweaters, shirts, trousers, skirts, etc.)

#### 2. `inventory_items` (Updated)
Nine new uniform items added (IDs: 11-19):
```
ID  | Name                              | Code        | Unit Cost
11  | School Sweater (All Sizes)        | UNF-SWTR    | 1,200
12  | School Socks (Pack of 3)          | UNF-SOCK    | 400
13  | School Shorts (All Sizes)         | UNF-SHRT    | 1,500
14  | School Trousers (All Sizes)       | UNF-TROU    | 2,000
15  | School Shirt (Boys All Sizes)     | UNF-SHRT-B  | 1,800
16  | School Blouse (Girls All Sizes)   | UNF-BLOU    | 1,800
17  | School Skirt (Girls All Sizes)    | UNF-SKRT    | 2,200
18  | Games Skirt (All Sizes)           | UNF-GAMS    | 1,500
19  | Sleeping Pajamas (All Sizes)      | UNF-PJMS    | 1,600
```

#### 3. `uniform_sizes` (New)
Tracks size availability for each uniform item:
```sql
CREATE TABLE uniform_sizes (
  id INT UNSIGNED PRIMARY KEY,
  item_id INT UNSIGNED NOT NULL (FK to inventory_items),
  size VARCHAR(20) - XS to XXL,
  quantity_available INT - Current stock for size,
  quantity_reserved INT - Held for orders,
  quantity_sold INT - Total sold,
  unit_price DECIMAL(10,2),
  last_restocked DATETIME,
  created_at TIMESTAMP,
  updated_at TIMESTAMP
);

Unique Key: (item_id, size)
```

#### 4. `uniform_sales` (New)
Records every uniform sale transaction:
```sql
CREATE TABLE uniform_sales (
  id INT UNSIGNED PRIMARY KEY,
  student_id INT UNSIGNED (FK to students),
  item_id INT UNSIGNED (FK to inventory_items),
  size VARCHAR(20) - XS to XXL,
  quantity INT DEFAULT 1,
  unit_price DECIMAL(10,2),
  total_amount DECIMAL(10,2),
  payment_status ENUM('paid', 'pending', 'partial'),
  sale_date DATE,
  received_date DATE,
  sold_by INT UNSIGNED (FK to users - staff),
  notes TEXT,
  created_at TIMESTAMP,
  updated_at TIMESTAMP
);

Indexes: student_id, item_id, sale_date, payment_status
```

#### 5. `student_uniforms` (New)
Stores uniform size preferences for each student:
```sql
CREATE TABLE student_uniforms (
  id INT UNSIGNED PRIMARY KEY,
  student_id INT UNSIGNED UNIQUE (FK to students),
  uniform_size VARCHAR(20),
  shirt_size VARCHAR(20),
  trousers_size VARCHAR(20),
  skirt_size VARCHAR(20),
  sweater_size VARCHAR(20),
  shoes_size VARCHAR(10),
  notes TEXT,
  last_updated TIMESTAMP,
  updated_by INT UNSIGNED (FK to users)
);
```

#### 6. `uniform_sales_summary` (New)
Pre-aggregated sales metrics for reporting:
```sql
CREATE TABLE uniform_sales_summary (
  id INT UNSIGNED PRIMARY KEY,
  month_year DATE,
  item_id INT UNSIGNED (FK to inventory_items),
  total_sales_count INT,
  total_sales_amount DECIMAL(12,2),
  total_paid DECIMAL(12,2),
  total_pending DECIMAL(12,2),
  total_partial DECIMAL(12,2),
  created_at TIMESTAMP,
  updated_at TIMESTAMP
);

Unique Key: (month_year, item_id)
```

---

## Stored Procedures

### 1. `sp_register_uniform_sale`
Registers a new uniform sale with automatic inventory updates.

**Parameters:**
- `p_student_id` - Student ID
- `p_item_id` - Uniform item ID
- `p_size` - Size (XS-XXL)
- `p_quantity` - Quantity sold
- `p_unit_price` - Price per unit
- `p_sold_by` - Staff ID who made sale
- `p_notes` - Additional notes

**Actions:**
1. Insert sale record into `uniform_sales`
2. Create inventory transaction (stock out)
3. Update `uniform_sizes` quantity tracking
4. Update `inventory_items` current quantity

### 2. `sp_mark_uniform_sale_paid`
Updates payment status for a uniform sale.

**Parameters:**
- `p_sale_id` - Sale ID
- `p_payment_status` - paid | pending | partial

---

## Database Views

### `vw_uniform_sales_analytics`
Comprehensive uniform sales view for reporting and analytics:

**Columns:**
- sale_id, student_id, student_name, admission_number
- uniform_item, item_code, size, quantity, unit_price, total_amount
- payment_status, payment_status_label
- sale_date, received_date, days_since_sale
- sold_by_first_name, sold_by_last_name

**Use:** Analytics, reporting, dashboards

---

## Triggers

### `trg_uniform_sale_insert`
Automatically updates `uniform_sales_summary` when a new sale is recorded:
- Increments total_sales_count
- Updates total_sales_amount
- Updates payment status breakdowns (paid, pending, partial)

---

## API Endpoints

### Base URL
```
/api/inventory/uniforms
```

### 1. List All Uniform Items
```
GET /api/inventory/uniforms

Response:
{
  "success": true,
  "message": "Uniform items retrieved",
  "data": {
    "items": [
      {
        "id": 11,
        "name": "School Sweater (All Sizes)",
        "code": "UNF-SWTR",
        "unit_cost": 1200,
        "total_stock": 200,
        "available_sizes": 6,
        "total_available": 180,
        "total_sold": 45,
        "status": "active"
      },
      ...
    ],
    "total_count": 9
  }
}
```

### 2. Get Size Variants for Item
```
GET /api/inventory/uniforms/{item_id}/sizes

Example: GET /api/inventory/uniforms/11/sizes

Response:
{
  "success": true,
  "data": {
    "item": {
      "id": 11,
      "name": "School Sweater (All Sizes)",
      "code": "UNF-SWTR",
      ...
    },
    "sizes": [
      {
        "id": 1,
        "size": "XS",
        "quantity_available": 15,
        "quantity_sold": 5,
        "unit_price": 1200,
        "last_restocked": "2025-12-28T10:30:00"
      },
      {
        "size": "S",
        "quantity_available": 20,
        ...
      },
      ...
    ]
  }
}
```

### 3. Register Uniform Sale
```
POST /api/inventory/uniforms/sales

Request:
{
  "student_id": 5,
  "item_id": 11,
  "size": "M",
  "quantity": 1,
  "unit_price": 1200,
  "sold_by": 3,
  "notes": "Purchased by parent"
}

Response:
{
  "success": true,
  "message": "Uniform sale registered successfully",
  "data": {
    "student_id": 5,
    "item_id": 11,
    "size": "M",
    "quantity": 1,
    "total_amount": 1200
  }
}
```

### 4. Get Student Uniform Sales History
```
GET /api/inventory/uniforms/sales/{student_id}

Example: GET /api/inventory/uniforms/sales/5

Response:
{
  "success": true,
  "data": {
    "sales": [
      {
        "id": 1,
        "item_id": 11,
        "item_name": "School Sweater (All Sizes)",
        "size": "M",
        "quantity": 1,
        "unit_price": 1200,
        "total_amount": 1200,
        "payment_status": "pending",
        "sale_date": "2025-12-28",
        "received_date": null,
        "notes": ""
      },
      {
        "id": 2,
        "item_id": 15,
        "item_name": "School Shirt (Boys All Sizes)",
        "size": "M",
        "quantity": 2,
        "unit_price": 1800,
        "total_amount": 3600,
        "payment_status": "paid",
        "sale_date": "2025-12-25",
        "received_date": "2025-12-25"
      }
    ],
    "summary": {
      "total_sales_count": 2,
      "total_amount": 4800,
      "paid_amount": 3600,
      "pending_amount": 1200
    }
  }
}
```

### 5. Update Payment Status
```
PUT /api/inventory/uniforms/sales/{sale_id}/payment

Request:
{
  "payment_status": "paid"  // OR "pending" OR "partial"
}

Response:
{
  "success": true,
  "data": {
    "sale_id": 1,
    "payment_status": "paid"
  }
}
```

### 6. Uniform Sales Dashboard
```
GET /api/inventory/uniforms/dashboard

Response:
{
  "success": true,
  "data": {
    "monthly_metrics": {
      "total_sales": 45,
      "total_revenue": 95400,
      "paid_amount": 75200,
      "pending_amount": 20200
    },
    "top_selling_items": [
      {
        "id": 15,
        "name": "School Shirt (Boys All Sizes)",
        "sales_count": 18,
        "total_quantity": 20,
        "total_amount": 36000
      },
      ...
    ],
    "inventory_status": {
      "total_items": 9,
      "in_stock": 8,
      "low_stock": 1,
      "out_of_stock": 0
    }
  }
}
```

### 7. Payment Summary
```
GET /api/inventory/uniforms/payments/summary

Response:
{
  "success": true,
  "data": {
    "payment_summary": [
      {
        "payment_status": "paid",
        "total_sales": 30,
        "total_amount": 75200,
        "unique_students": 28
      },
      {
        "payment_status": "pending",
        "total_sales": 12,
        "total_amount": 20200,
        "unique_students": 10
      },
      {
        "payment_status": "partial",
        "total_sales": 3,
        "total_amount": 5000,
        "unique_students": 3
      }
    ]
  }
}
```

### 8. Update Student Uniform Profile
```
PUT /api/inventory/uniforms/students/{student_id}/profile

Request:
{
  "uniform_size": "M",
  "shirt_size": "M",
  "trousers_size": "32",
  "skirt_size": "M",
  "sweater_size": "M",
  "shoes_size": "10"
}

Response:
{
  "success": true,
  "data": {
    "student_id": 5,
    "sizes": { ... }
  }
}
```

### 9. Get Student Uniform Profile
```
GET /api/inventory/uniforms/students/{student_id}/profile

Response:
{
  "success": true,
  "data": {
    "id": 1,
    "student_id": 5,
    "uniform_size": "M",
    "shirt_size": "M",
    "trousers_size": "32",
    "skirt_size": "M",
    "sweater_size": "M",
    "shoes_size": "10",
    "notes": null,
    "last_updated": "2025-12-28T10:30:00"
  }
}
```

---

## Frontend Implementation

### Features to Add

#### 1. Uniform Inventory Management
- View all uniform items with stock levels
- Add/edit/delete uniform items
- Manage size variants and pricing
- Stock reordering with automatic low stock alerts

#### 2. Uniform Sales Interface
- Quick sale registration form
- Student search and selection
- Size and quantity selection
- Payment status tracking
- Sales history view

#### 3. Student Uniform Profiles
- Store student size preferences
- Quick profile access during sales
- Size recommendations based on form

#### 4. Sales Dashboard
- Monthly sales metrics (count, revenue)
- Payment status breakdown (paid, pending, partial)
- Top selling items
- Stock status indicators
- Sales trends

#### 5. Reports
- Student uniform expenditure reports
- Monthly sales reports
- Outstanding payment reports
- Inventory valuation reports

---

## Implementation Steps

### Step 1: Apply Database Migration
```bash
# Run the migration file
mysql -u username -p database_name < database/migrations/add_uniform_sales_module.sql
```

### Step 2: Module Installation
1. `UniformSalesManager.php` already created at: `api/modules/inventory/UniformSalesManager.php`
2. API endpoints already added to: `api/controllers/InventoryController.php`

### Step 3: Update Frontend
1. Update `manage_inventory.php` with uniform-specific UI
2. Create `manage_uniform_sales.php` for sales registration
3. Add uniform section to inventory dashboard

### Step 4: Permissions
Existing permissions already configured:
- `inventory_uniforms_view` - View uniform inventory
- `inventory_uniforms_manage` - Manage uniform sales

### Step 5: Testing
Use API Explorer to test endpoints:
```
GET /api/inventory/uniforms
POST /api/inventory/uniforms/sales
GET /api/inventory/uniforms/dashboard
```

---

## Security & Access Control

### Role-Based Access
- **Inventory Manager** (Role ID: 14) - Full access to uniform management
- **Director** - View sales reports and payment status
- **School Admin** - Limited access to sales management
- **Finance/Accountant** - Payment tracking and reconciliation

### Data Validation
- Student existence verification
- Stock availability checking
- Payment status validation
- Input sanitization

### Audit Trail
- All sales logged via `inventory_transactions`
- Staff member tracking (sold_by)
- Timestamp recording for all changes

---

## Reporting Features

### Available Reports
1. **Uniform Inventory Report** - Stock levels by size
2. **Sales Report** - Sales count and amount by period
3. **Payment Status Report** - Outstanding and paid amounts
4. **Student Uniform History** - Purchase history per student
5. **Top Selling Items** - Best-performing uniforms
6. **Revenue Analysis** - Sales trends over time

---

## Performance Considerations

### Indexes
- `uniform_sales.student_id` - Fast student lookup
- `uniform_sales.item_id` - Fast item lookup
- `uniform_sizes.item_id` - Fast size availability check
- `uniform_sales.sale_date` - Fast date range queries

### Query Optimization
- Use `uniform_sales_summary` for monthly aggregations
- Leverage `vw_uniform_sales_analytics` for reporting
- Batch operations for bulk updates

---

## Future Enhancements

1. **Size Recommendations** - Auto-suggest sizes based on age/grade
2. **Bulk Orders** - Support class-wide uniform distribution
3. **Supplier Integration** - Automatic reordering
4. **QR Code Tracking** - Track uniforms with QR codes
5. **Return Processing** - Handle size exchanges and returns
6. **Student Portal** - Allow students to order uniforms online

---

## Support

For issues or questions:
1. Check database logs: `api/logs/`
2. Review stored procedures: `database/KingsWayAcademyDatabase.sql`
3. Test API endpoints: Use API Explorer tool
4. Contact: System Administrator

---

**Version:** 1.0.0  
**Last Updated:** 28 December 2025  
**Database Version:** MySQL 5.7+  
**PHP Version:** 7.4+
