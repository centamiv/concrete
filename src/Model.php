<?php

namespace Concrete;

abstract class Model
{
    /**
     * The model's attributes.
     *
     * @var array
     */
    protected $attributes = [];

    /**
     * The model's original attributes.
     *
     * @var array
     */
    protected $original = [];

    /**
     * The table associated with the model.
     *
     * @var string
     */
    public const TABLE = '';

    /**
     * The primary key for the model.
     *
     * @var string|array
     */
    public const PRIMARY_KEY = 'id';

    /**
     * Create a new model instance.
     *
     * @param array $data
     */
    public function __construct(array $data = [])
    {
        $this->fill($data);
    }

    /**
     * Get the primary key names as an array.
     *
     * @return array
     */
    protected function getPrimaryKeyNames(): array
    {
        return (array) static::PRIMARY_KEY;
    }

    /**
     * Determine if the model exists in the database.
     *
     * @return bool
     */
    public function exists(): bool
    {
        $pks = $this->getPrimaryKeyNames();
        foreach ($pks as $pk) {
            if (empty($this->attributes[$pk])) {
                return false;
            }
        }
        return true;
    }

    /**
     * Fill the model with an array of attributes.
     *
     * @param array $data
     * @return void
     */
    public function fill(array $data)
    {
        foreach ($data as $key => $value) {
            $this->attributes[$key] = $value;
        }
    }

    /**
     * Hydrate the model with data from the database.
     *
     * @param array $data
     * @return void
     */
    public function hydrate(array $data)
    {
        $this->fill($data);
        $this->syncOriginal();
    }

    /**
     * Sync the original attributes with the current.
     *
     * @return void
     */
    public function syncOriginal()
    {
        $this->original = $this->attributes;
    }

    /**
     * Get the attributes that have been changed since the last sync.
     *
     * @return array
     */
    public function getDirty(): array
    {
        $dirty = [];
        foreach ($this->attributes as $key => $value) {
            if (!array_key_exists($key, $this->original) || $this->original[$key] !== $value) {
                $dirty[$key] = $value;
            }
        }
        return $dirty;
    }

    /**
     * Get an attribute from the model.
     *
     * @param string $field
     * @return mixed
     */
    public function get(string $field)
    {
        return $this->attributes[$field] ?? null;
    }

    /**
     * Set a given attribute on the model.
     *
     * @param string $field
     * @param mixed $value
     * @return $this
     */
    public function set(string $field, $value)
    {
        $this->attributes[$field] = $value;
        return $this; // Fluent interface
    }

    /**
     * Get the fully qualified column name.
     *
     * @param string $column
     * @param string|null $alias Table alias
     * @return string
     */
    public static function col(string $column, ?string $alias = null): string
    {
        $prefix = $alias ?? static::TABLE;
        return $prefix . '.' . $column;
    }

    /**
     * Get the fully qualified column name with an alias (AS ...).
     *
     * @param string $column
     * @param string $columnAs Column alias (AS ...)
     * @param string|null $tableAlias Table alias
     * @return string
     */
    public static function colAs(string $column, string $columnAs, ?string $tableAlias = null): string
    {
        return self::col($column, $tableAlias) . " as $columnAs";
    }

    /**
     * Begin querying the model.
     *
     * @return \Concrete\Query\Builder
     */
    public static function query(): \Concrete\Query\Builder
    {
        $builder = new \Concrete\Query\Builder();
        $builder->table(static::class);
        return $builder;
    }

    /**
     * Find a model by its primary key.
     *
     * @param mixed $id
     * @return static|null
     * @throws \Exception
     */
    public static function find($id)
    {
        $query = new Query\Builder();
        $query->table(static::class);

        $pks = (array) static::PRIMARY_KEY;

        // If the key is composite, the user MUST pass an associative array
        // Ex: UserRole::find(['user_id' => 10, 'role_id' => 5]);
        if (is_array($id)) {
            foreach ($pks as $pk) {
                if (!isset($id[$pk])) {
                    throw new \Exception("Incomplete composite key: missing $pk");
                }
                $query->where(static::col($pk), '=', $id[$pk]);
            }
        } else {
            $query->where(static::col($pks[0]), '=', $id);
        }

        return $query->get()[0] ?? null;
    }

    /**
     * Save the model to the database.
     *
     * @return $this
     */
    public function save()
    {
        $db = Database::getConnection();
        $table = static::TABLE;
        $data = $this->attributes;
        $pks = $this->getPrimaryKeyNames();

        if ($this->exists()) {

            // Only update dirty attributes
            $updateData = $this->getDirty();

            // Remove primary keys from fields to update (just in case they were marked dirty but shouldn't be changed if they are part of WHERE)
            $updateData = array_diff_key($updateData, array_flip($pks));

            if (empty($updateData)) {
                return $this;
            }

            $sets = array_map(fn($k) => "$k = :$k", array_keys($updateData));

            $wheres = [];
            foreach ($pks as $pk) {
                $wheres[] = "$pk = :pk_$pk"; // Use a prefix to avoid conflicts with names
                // Use ORIGINAL PK if available to find the record, otherwise current
                $val = $this->original[$pk] ?? $this->attributes[$pk];
                $updateData["pk_$pk"] = $val;
            }

            $sql = "UPDATE $table SET " . implode(', ', $sets) . " WHERE " . implode(' AND ', $wheres);

            $stmt = $db->prepare($sql);
            $stmt->execute($updateData);

        } else {

            $cols = implode(', ', array_keys($data));
            $placeholders = ':' . implode(', :', array_keys($data));
            $sql = "INSERT INTO $table ($cols) VALUES ($placeholders)";

            $stmt = $db->prepare($sql);
            $stmt->execute($data);

            // If the key is single and was not set (AUTO_INCREMENT), retrieve the ID
            if (count($pks) === 1 && empty($data[$pks[0]])) {
                $this->attributes[$pks[0]] = $db->lastInsertId();
            }
            // Note: With composite keys usually AUTO_INCREMENT is not used,
            // so lastInsertId() is not needed.
        }

        $this->syncOriginal();

        return $this;
    }

    /**
     * Delete the model from the database.
     *
     * @return bool
     */
    public function delete(): bool
    {
        if (!$this->exists()) {
            return false;
        }

        $query = static::query();
        $pks = $this->getPrimaryKeyNames();

        foreach ($pks as $pk) {
            $query->where($pk, '=', $this->attributes[$pk]);
        }

        return $query->delete() > 0;
    }
}
