# TRIWEAR

Premium campus merchandise storefront built with PHP, MySQL, Tailwind CSS, and vanilla JavaScript.

## Setup

1. Create a MySQL database, then import `database/schema.sql`.
2. Copy `.env.example` to `.env` and fill in the database credentials.
3. Serve this directory through Apache/PHP (XAMPP is supported).
4. Open `http://localhost/TRISTYLE/`.

The seeded administrator is `admin@triwear.test` with password `ChangeMe123!`. Change it immediately after first login.

## Important size model

Each product can have its own selectable sizes and size-chart rows. `P · Panjang` and `L · Lebar` are stored as the unique internal codes `P_LENGTH` and `L_WIDTH`; they are separate from the conventional `L` apparel size. The selected code and display name are persisted into cart items and order items.
