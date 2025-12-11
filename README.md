# Concrete ORM

**Concrete** is a simple and solid ORM for PHP applications. It provides an elegant Active Record implementation and a fluent Query Builder to interact with your database.

## Installation

You can install the package via composer:

```bash
composer require centamiv/concrete
```

## Configuration

To start using Concrete, you need to initialize the database connection. Typically, you would do this in your application's bootstrap file (e.g., `index.php`).

### MySQL

```php
use Concrete\Database;
use Concrete\Connection\MysqlDriver;

require 'vendor/autoload.php';

// Initialize the database connection
Database::init(
    new MysqlDriver(),
    '127.0.0.1', // Host
    'my_database', // Database Name
    'root',      // User
    'password'   // Password
);
```

### SQLite

```php
use Concrete\Database;
use Concrete\Connection\SqliteDriver;

require 'vendor/autoload.php';

// Initialize the database connection
// For SQLite, the second parameter is the database file path
Database::init(
    new SqliteDriver(),
    '',                        // Host (not used for SQLite)
    '/path/to/database.sqlite', // Database file path (or ':memory:' for in-memory)
    '',                        // User (not used for SQLite)
    ''                         // Password (not used for SQLite)
);
```

### PostgreSQL

```php
use Concrete\Database;
use Concrete\Connection\PostgresDriver;

require 'vendor/autoload.php';

// Initialize the database connection
Database::init(
    new PostgresDriver(),
    '127.0.0.1',     // Host
    'my_database',   // Database Name
    'postgres',      // User
    'password'       // Password
);
```

### SQL Server

```php
use Concrete\Database;
use Concrete\Connection\SqlServerDriver;

require 'vendor/autoload.php';

// Initialize the database connection
Database::init(
    new SqlServerDriver(),
    'localhost',     // Host (Server)
    'my_database',   // Database Name
    'sa',            // User
    'password'       // Password
);
```

## Usage

### Defining Models

Create a model class that extends `Concrete\Model`. You need to define the `TABLE` constant. By default, the primary key is assumed to be `id`.

```php
namespace App\Models;

use Concrete\Model;

class User extends Model
{
    public const TABLE = 'users';

    public const COL_ID = 'id';
    public const COL_NAME = 'name';
    public const COL_EMAIL = 'email';
    public const COL_AGE = 'age';
    public const COL_ACTIVE = 'active';
    public const COL_ROLE_ID = 'role_id';
    public const COL_CREATED_AT = 'created_at';
}
```

#### Custom Primary Keys

If your table uses a primary key other than `id`, you can override the `PRIMARY_KEY` constant. Concrete also supports **composite primary keys**.

```php
class Order extends Model
{
    public const TABLE = 'orders';
    
    // Simple Primary Key
    public const PRIMARY_KEY = 'id';
}


class OrderItem extends Model
{
    public const TABLE = 'order_items';
    
    // Composite Primary Key
    public const PRIMARY_KEY = ['order_id', 'product_id'];
}
```

### Basic CRUD

#### Creating Records

To create a new record, instantiate your model, set attributes, and call `save()`.

```php
$user = new User();
$user->set(User::COL_NAME, 'Mario Rossi')
     ->set(User::COL_EMAIL, 'mario@example.com')
     ->save();

// Access the auto-incremented ID (if applicable)
echo $user->get(User::COL_ID); 
```

#### Retrieving Records

Use the `find()` static method to retrieve a record by its primary key.

```php
// Find by single primary key
$user = User::find(1);

if ($user) {
    echo $user->get(User::COL_NAME);
}

// Find by composite primary key
$item = OrderItem::find(['order_id' => 10, 'product_id' => 5]);
```

#### Updating Records

Retrieve a record, modify its attributes, and call `save()` again.

```php
$user = User::find(1);
$user->set(User::COL_EMAIL, 'new_email@example.com');
$user->save();
```

#### Deleting Records

Retrieve a record and call `delete()`.

```php
$user = User::find(1);
if ($user) {
    $user->delete();
}
```

### Query Builder

Concrete provides a fluent Query Builder for more complex queries. You can access it via the `query()` static method on your models.

#### Filtering (`where`)

```php
$users = User::query()
    ->where(User::col(User::COL_ACTIVE), '=', 1)
    ->where(User::col(User::COL_AGE), '>', 18)
    ->get();

foreach ($users as $user) {
    echo $user->get(User::COL_NAME);
}
```

#### Ordering (`orderBy`)

```php
$users = User::query()
    ->where(User::col(User::COL_ACTIVE), '=', 1)
    ->orderBy(User::col(User::COL_CREATED_AT), 'DESC')
    ->get();
```

#### Joins (`join`, `leftJoin`)

You can join other tables using the `join` or `leftJoin` methods.

```php
// Assuming Role model exists with constants
$users = User::query()
    ->select(User::col('*'), Role::colAs(Role::COL_NAME, 'role_name'))
    ->join(Role::TABLE, User::col(User::COL_ROLE_ID), '=', Role::col(Role::COL_ID))
    ->get();
```

You can also use `rightJoin`:

```php
$users = User::query()
    ->rightJoin(Role::TABLE, User::col(User::COL_ROLE_ID), '=', Role::col(Role::COL_ID))
    ->get();
```

#### Updating Records (`update`)

```php
$affected = User::query()
    ->where(User::col(User::COL_ACTIVE), '=', 0)
    ->update([User::col(User::COL_ACTIVE) => 1]);
```

#### Deleting Records (`delete`)

```php
$deleted = User::query()
    ->where(User::col(User::COL_AGE), '<', 18)
    ->delete();
```

#### Limiting Results (`take`, `skip`)

```php
$users = User::query()
    ->take(10) // LIMIT 10
    ->skip(5)  // OFFSET 5
    ->get();
```

#### Aggregates & Helpers

- **`count()`**: Returns the number of records matching the query.
- **`first()`**: Returns the first model instance or `null`.
- **`exists()`**: Returns `true` if any records match the query.

```php
$count = User::query()->where(User::col(User::COL_ACTIVE), '=', 1)->count();

$user = User::query()->where(User::col(User::COL_EMAIL), '=', 'test@example.com')->first();

if (User::query()->where(User::col(User::COL_AGE), '<', 18)->exists()) {
    // ...
}
```

#### Fetching Arrays

If you prefer to work with raw associative arrays instead of Model instances, you can use `getRows()` or `firstRow()`.

```php
// Returns an array of associative arrays
$users = User::query()->where('active', '=', 1)->getRows();
foreach ($users as $user) {
    echo $user['name']; // Access as array
}

// Returns a single associative array or null
$user = User::query()->find(1)->firstRow();
if ($user) {
    echo $user['email'];
}
```

### Helper Methods

- **`exists()`**: Checks if the model instance exists in the database (based on primary key presence).
- **`fill(array $data)`**: Mass assign attributes from an array.
- **`col(string $column, ?string $alias = null)`**: Helper to get fully qualified column names (e.g., `users.name`).
- **`colAs(string $column, string $columnAs, ?string $tableAlias = null)`**: Helper to get fully qualified column names with an alias (e.g., `users.name as user_name`).

## Advanced Usage

### Debugging Queries

You can inspect the generated SQL query using the `sql()` method on the builder.

```php
$query = User::query()
    ->where(User::col(User::COL_ACTIVE), '=', 1);

echo $query->sql(); 
// Outputs: SELECT users.* FROM users WHERE users.active = :users_active0
```

### Raw Queries

For complex operations not supported by the Query Builder, you can access the underlying PDO connection.

```php
use Concrete\Database;

$db = Database::getConnection();

// Complex Raw Query
$stmt = $db->query("SELECT count(*) as count FROM users GROUP BY age");
$stats = $stmt->fetchAll();
```

### Standalone Query Builder

While `Model::query()` is the preferred way, you can use the `Builder` directly.

```php
use Concrete\Query\Builder;
use App\Models\User;

$builder = new Builder();
$users = $builder->table(User::class)
    ->where('active', '=', 1)
    ->get();
```

## Requirements

- PHP >= 7.4
- `ext-pdo` extension
- For MySQL: `ext-pdo_mysql`
- For SQLite: `ext-pdo_sqlite`
- For PostgreSQL: `ext-pdo_pgsql`
- For SQL Server: `ext-pdo_sqlsrv`

## Appendix: Laravel Integration

If you want to use **Concrete** within a Laravel application and reuse the existing Laravel database connection, you can use the `initFromPDO` method.

### 1. Initialize in AppServiceProvider

In your `app/Providers/AppServiceProvider.php`, initialize Concrete in the `boot` method.

```php
use Concrete\Database;
use Concrete\Connection\MysqlDriver;
// Or use: PostgresDriver, SqliteDriver, SqlServerDriver
use Illuminate\Support\Facades\DB;

public function boot()
{
    // Initialize using the existing Laravel PDO connection
    // Choose the appropriate driver based on your Laravel database configuration
    Database::initFromPDO(
        DB::connection()->getPdo(),
        new MysqlDriver() // Use PostgresDriver, SqliteDriver, or SqlServerDriver as needed
    );
}
```

### 2. Usage Example

Now you can use Concrete models alongside Eloquent models.

```php
namespace App\Http\Controllers;

use App\Models\Concrete\User; // Your Concrete Model

class UserController extends Controller
{
    public function index()
    {
        // Use Concrete to fetch users using Laravel's connection
        $users = User::query()
            ->where(User::col(User::COL_ACTIVE), '=', 1)
            ->get();

        return view('users.index', ['users' => $users]);
    }
}
```

## License

This library is open-sourced software licensed under the MIT license.
