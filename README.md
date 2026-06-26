# Concrete ORM

**Concrete** is a simple and solid ORM for PHP applications. It provides an elegant Active Record implementation and a fluent Query Builder to interact with your database.

## Installation

```bash
composer require centamiv/concrete
```

## Configuration

Initialize the database connection once, typically in your bootstrap file.

### MySQL

```php
use Concrete\Database;
use Concrete\Connection\MysqlDriver;

Database::init(new MysqlDriver(), '127.0.0.1', 'my_database', 'root', 'password');
```

### SQLite

```php
use Concrete\Database;
use Concrete\Connection\SqliteDriver;

// Second parameter is the file path (or ':memory:' for in-memory)
Database::init(new SqliteDriver(), '/path/to/database.sqlite');
```

### PostgreSQL

```php
use Concrete\Database;
use Concrete\Connection\PostgresDriver;

Database::init(new PostgresDriver(), '127.0.0.1', 'my_database', 'postgres', 'password');
```

### SQL Server

```php
use Concrete\Database;
use Concrete\Connection\SqlServerDriver;

Database::init(new SqlServerDriver(), 'localhost', 'my_database', 'sa', 'password');
```

---

## Defining Models

Extend `Concrete\Model` and declare the `TABLE` constant. The primary key defaults to `id`.

```php
namespace App\Models;

use Concrete\Model;

class User extends Model
{
    public const TABLE = 'users';

    // Optional: column name constants for type-safe references
    public const COL_ID         = 'id';
    public const COL_NAME       = 'name';
    public const COL_EMAIL      = 'email';
    public const COL_AGE        = 'age';
    public const COL_ACTIVE     = 'active';
    public const COL_ROLE_ID    = 'role_id';
    public const COL_CREATED_AT = 'created_at';
}
```

### Custom & Composite Primary Keys

```php
class Order extends Model
{
    public const TABLE       = 'orders';
    public const PRIMARY_KEY = 'order_code'; // custom single key
}

class OrderItem extends Model
{
    public const TABLE       = 'order_items';
    public const PRIMARY_KEY = ['order_id', 'product_id']; // composite key
}
```

---

## CRUD

### Create

```php
$user = new User();
$user->set(User::COL_NAME, 'Mario Rossi')
     ->set(User::COL_EMAIL, 'mario@example.com')
     ->save();

echo $user->get(User::COL_ID); // auto-incremented ID
```

### Read

```php
// Single primary key
$user = User::find(1);
echo $user?->get(User::COL_NAME);

// Composite primary key
$item = OrderItem::find(['order_id' => 10, 'product_id' => 5]);
```

### Update

```php
$user = User::find(1);
$user->set(User::COL_EMAIL, 'new@example.com');
$user->save(); // only dirty attributes are sent to the database
```

### Delete

```php
$user = User::find(1);
$user?->delete();
```

---

## Query Builder

Access the builder via `Model::query()`.

### Filtering

#### `where`

```php
$users = User::query()
    ->where(User::col(User::COL_ACTIVE), '=', 1)
    ->where(User::col(User::COL_AGE), '>', 18)
    ->get();
```

Supported operators: `=` `!=` `<>` `<` `>` `<=` `>=` `LIKE` `NOT LIKE` `IN` `NOT IN` `IS` `IS NOT`

#### `whereIn` / `whereNotIn`

Pass a plain array or a **subquery Builder** as the second argument.

```php
// Array literal
$users = User::query()
    ->whereIn(User::col(User::COL_ROLE_ID), [1, 2, 3])
    ->get();

// Subquery
$users = User::query()
    ->whereIn(
        User::col(User::COL_ID),
        Order::query()
            ->select('user_id')
            ->where(Order::col('status'), '=', 'paid')
    )
    ->get();

// NOT IN
$users = User::query()
    ->whereNotIn(User::col(User::COL_ID), [10, 20])
    ->get();
```

> An empty array in `whereIn` generates `1 = 0` (always false); in `whereNotIn` it generates `1 = 1` (always true).

#### `whereExists` / `whereNotExists`

```php
$users = User::query()
    ->whereExists(
        Order::query()
            ->whereColumn(Order::col('user_id'), '=', User::col(User::COL_ID))
            ->where(Order::col('status'), '=', 'paid')
    )
    ->get();
```

#### `whereColumn`

Compare two column identifiers without parameterization — used for **correlated subqueries**.

```php
Order::query()->whereColumn(Order::col('user_id'), '=', User::col(User::COL_ID));
```

Supported operators: `=` `!=` `<>` `<` `>` `<=` `>=`

---

### Ordering

```php
$users = User::query()
    ->orderBy(User::col(User::COL_CREATED_AT), 'DESC')
    ->get();
```

---

### Joins

```php
$users = User::query()
    ->select(User::col('*'), Role::colAs('name', 'role_name'))
    ->join(Role::TABLE, User::col(User::COL_ROLE_ID), '=', Role::col('id'))
    ->get();

// Left / right join
User::query()->leftJoin(Role::TABLE, User::col(User::COL_ROLE_ID), '=', Role::col('id'));
User::query()->rightJoin(Role::TABLE, User::col(User::COL_ROLE_ID), '=', Role::col('id'));
```

---

### Conditional Selection — `CASE`

Build `CASE WHEN … THEN … ELSE … END` expressions with `When`. Pass them directly to `select()`.

```php
use Concrete\Query\When;

$users = User::query()
    ->select(
        User::col(User::COL_ID),
        User::col(User::COL_NAME),
        When::make()
            ->when(User::col(User::COL_ACTIVE), '=', 1)->then('Active')
            ->when(User::col(User::COL_ACTIVE), '=', 0)->then('Inactive')
            ->else('Unknown')
            ->as('status_label'),
        When::make()
            ->when(User::col(User::COL_AGE), '>=', 18)->then(1)
            ->else(0)
            ->as('is_adult')
    )
    ->get();
```

**Result type rules for `then()` / `else()`:**

| PHP type | SQL |
|----------|-----|
| `int` / `float` | embedded literal (`1`, `3.14`) |
| `null` | `NULL` literal |
| `string` | bound as PDO named parameter |

---

### Scalar Subqueries in `select()`

Use `Subquery` to embed a correlated scalar subquery as a column.

```php
use Concrete\Query\Subquery;

$users = User::query()
    ->select(
        User::col(User::COL_ID),
        User::col(User::COL_NAME),
        Subquery::make(
            Order::query()
                ->select('COUNT(*)')
                ->whereColumn(Order::col('user_id'), '=', User::col(User::COL_ID))
        )->as('order_count')
    )
    ->get();
// → SELECT users.id, users.name,
//     (SELECT COUNT(*) FROM orders WHERE orders.user_id = users.id) AS order_count
//   FROM users
```

---

### UNION

Combine the results of two or more queries with `union()` (distinct rows) or `unionAll()` (all rows including duplicates).

```php
$activeUsers = User::query()
    ->select(User::col(User::COL_ID), User::col(User::COL_NAME))
    ->where(User::col(User::COL_ACTIVE), '=', 1);

$adminUsers = User::query()
    ->select(User::col(User::COL_ID), User::col(User::COL_NAME))
    ->where(User::col(User::COL_ROLE_ID), '=', 99);

// Distinct rows
$result = $activeUsers
    ->union($adminUsers)
    ->orderBy(User::col(User::COL_NAME), 'ASC')
    ->take(20)
    ->get();

// Including duplicates
$result = $activeUsers->unionAll($adminUsers)->get();
```

`orderBy()`, `take()`, and `skip()` called on the **outermost** builder apply to the whole union result.

---

### Limiting Results

```php
$users = User::query()
    ->take(10)  // LIMIT 10
    ->skip(20)  // OFFSET 20
    ->get();
```

---

### Aggregates & Helpers

```php
// Total count
$total = User::query()->where(User::col(User::COL_ACTIVE), '=', 1)->count();

// First matching record
$user = User::query()->where(User::col(User::COL_EMAIL), '=', 'mario@example.com')->first();

// Check existence
$exists = User::query()->where(User::col(User::COL_AGE), '<', 18)->exists();
```

---

### Bulk Update & Delete via Builder

```php
// Update active flag for all inactive users
User::query()
    ->where(User::col(User::COL_ACTIVE), '=', 0)
    ->update([User::COL_ACTIVE => 1]);

// Delete underage users
User::query()
    ->where(User::col(User::COL_AGE), '<', 18)
    ->delete();
```

---

### Raw Arrays

Use `getRows()` / `firstRow()` to retrieve plain associative arrays instead of model instances.

```php
$rows = User::query()->where(User::col(User::COL_ACTIVE), '=', 1)->getRows();
foreach ($rows as $row) {
    echo $row['name'];
}

$row = User::query()->where(User::col(User::COL_EMAIL), '=', 'mario@example.com')->firstRow();
echo $row['email'] ?? 'not found';
```

---

## Advanced

### Inspecting the Generated SQL

```php
$sql = User::query()
    ->where(User::col(User::COL_ACTIVE), '=', 1)
    ->sql();

// SELECT * FROM users WHERE users.active = :users_active0
```

### Raw PDO Queries

```php
use Concrete\Database;

$stmt = Database::getConnection()->query('SELECT COUNT(*) as n FROM users GROUP BY age');
$rows = $stmt->fetchAll();
```

### Standalone Builder

```php
use Concrete\Query\Builder;

$builder = new Builder();
$users = $builder->table(User::class)
    ->where(User::col(User::COL_ACTIVE), '=', 1)
    ->get();
```

---

## Requirements

- PHP >= 7.4
- `ext-pdo`
- For MySQL: `ext-pdo_mysql`
- For SQLite: `ext-pdo_sqlite`
- For PostgreSQL: `ext-pdo_pgsql`
- For SQL Server: `ext-pdo_sqlsrv`

---

## Laravel Integration

Reuse an existing Laravel PDO connection via `initFromPDO`.

```php
// app/Providers/AppServiceProvider.php
use Concrete\Database;
use Concrete\Connection\MysqlDriver;
use Illuminate\Support\Facades\DB;

public function boot(): void
{
    Database::initFromPDO(
        DB::connection()->getPdo(),
        new MysqlDriver()
    );
}
```

Then use Concrete models alongside Eloquent in your controllers.

---

## License

MIT
