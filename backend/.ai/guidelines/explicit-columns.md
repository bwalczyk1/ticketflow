# Explicit columns in queries

- Every query written in `app/` selects explicit columns. Never fetch `select *` or `table.*`.
- Select only the columns the code, policies and API resources read. Always include the primary key and every foreign key that a relation, a policy or later code needs.
- Use `select([...])`, `get([...])`, `first([...])`, `find($id, [...])` or `paginate(perPage: ..., columns: [...])`.
- Eager loads list their columns too, and nested relations keep the keys that join them.
- Counts, `exists()`, sums and conditional updates do not fetch rows and need no column list.
- Framework internals (Sanctum token lookup, the session user provider, route model binding, queue, Horizon, migrations) stay as they are.
- `Model::preventAccessingMissingAttributes()` throws outside production when code reads a column that was not selected, and `tests/TestCase.php` fails any test in which code in `app/` runs a `select *` query.

```php
$users = User::query()
    ->orderBy('id')
    ->paginate(perPage: $limit, columns: ['id', 'name', 'email', 'role', 'created_at']);
```

```php
$order = Order::query()
    ->with([
        'event:id,title,starts_at,timezone',
        'items:id,order_id,ticket_type_id,quantity,unit_price',
    ])
    ->lockForUpdate()
    ->findOrFail($orderId, ['id', 'user_id', 'event_id', 'status', 'total']);
```
