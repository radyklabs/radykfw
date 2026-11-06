<?php declare (strict_types=1);

namespace System\Core;

if (!defined ('ABSPATH'))
{
    http_response_code (404);
    exit ('<h1>404 Not Found</h1><p>The requested page could not be found.</p>');
}

/**
 * RadykFW Record — single-row wrapper with lazy relation access.
 *
 * Attribute access: $record->ID or $record['ID'].
 * Magic property access for declared relations (lazy-loaded on first
 * access via __get + ReflectionClass lookup of static model methods).
 *
 * @since 1.0.1
 */
class Record
{
    private ?\ReflectionClass $modelClass;
    private array $attributes;
    private string $key;
    /** @var array<string, mixed> */
    private array $relations = [];

    /**
     * @param class-string<Model>|string $modelClass Owner model class name
     * @param array $attributes                       Row data
     */
    public function __construct (
        string $modelClass,
        array $attributes
    ) {
        $this->modelClass = class_exists ($modelClass)
            ? new \ReflectionClass ($modelClass)
            : null;
        $this->attributes = $attributes;
        $this->key = 'id';
    }

    /**
     * Magic access: first try known attribute; then try declared
     * static relation methods on the owner Model.
     */
    public function __get (string $prop)
    {
        if (array_key_exists ($prop, $this->attributes))
        {
            return $this->attributes[$prop];
        }

        $method = $this->resolveRelationMethod ($prop);
        if ($method !== null)
        {
            return $this->loadRelation ($method);
        }

        return null;
    }

    public function __set (string $prop, mixed $value): void
    {
        $this->attributes[$prop] = $value;
    }

    public function __isset (string $prop): bool
    {
        return array_key_exists ($prop, $this->attributes);
    }

    /** All attributes as plain array. */
    public function __get_arr ()
    {
        return $this->attributes;
    }

    // ── ArrayAccess ──

    public function &offsetGet (string $offset): mixed
    {
        return $this->attributes[$offset];
    }

    public function offsetSet (string $offset, mixed $value): void
    {
        $this->attributes[$offset] = $value;
    }

    public function offsetExists (string $offset): bool
    {
        return array_key_exists ($offset, $this->attributes);
    }

    public function offsetUnset (string $offset): void
    {
        unset ($this->attributes[$offset]);
    }

    /** Cast to plain associative array. */
    public function toArray (): array
    {
        return $this->attributes;
    }

    /** Primary key value. */
    public function id (): int
    {
        return (int) ($this->attributes[$this->key] ?? 0);
    }

    // ── Relation loading ──

    private function resolveRelationMethod (string $prop): ?string
    {
        if ($this->modelClass === null) {return null;}
        if ($this->modelClass->hasMethod ($prop))
        {
            $method = $this->modelClass->getMethod ($prop);
            if ($method->isStatic ()
                && $method->getDeclaringClass ()->getName () !== Model::class)
            {
                return $prop;
            }
        }
        return null;
    }

    private function loadRelation (string $method)
    {
        if (array_key_exists ($method, $this->relations))
        {
            return $this->relations[$method];
        }

        if ($this->modelClass === null) {return null;}

        $reflection = $this->modelClass->getMethod ($method);
        $relation = $reflection->invoke (null);

        if ($relation instanceof Relation)
        {
            $result = $this->executeRelation ($relation);
            $this->relations[$method] = $result;
            return $result;
        }

        $this->relations[$method] = null;
        return null;
    }

    /**
     * Execute a Relation given this Record's primary-key value.
     */
    private function executeRelation (Relation $rel): mixed
    {
        $pk = $this->attributes[$rel->sourceKey ()] ?? null;
        if ($pk === null) {return null;}

        switch ($rel->type ())
        {
            case Relation::HAS_ONE:
                return $rel->recordForOne ($pk);
            case Relation::HAS_MANY:
                return $rel->recordsForMany ($pk);
            case Relation::BELONGS_TO:
                return $rel->recordForBelongsTo ($pk);
        }
        return null;
    }
}
