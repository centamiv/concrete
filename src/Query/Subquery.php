<?php

namespace Concrete\Query;

class Subquery
{
    private Builder $sub;
    private ?string $alias = null;
    private string $uid;
    private ?array $compiled = null;

    private static int $instances = 0;

    private function __construct(Builder $sub)
    {
        self::$instances++;
        $this->uid = 'sqe' . self::$instances;
        $this->sub = $sub;
    }

    public static function make(Builder $sub): self
    {
        return new self($sub);
    }

    /**
     * Set the column alias for this scalar subquery (AS …).
     *
     * @param string $alias
     * @return $this
     */
    public function as(string $alias): self
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $alias)) {
            throw new \InvalidArgumentException("Invalid alias: '$alias'");
        }
        $this->alias = $alias;
        return $this;
    }

    /**
     * Return the PDO parameters bound by the inner subquery (remapped to avoid collisions).
     *
     * @return array
     */
    public function getParams(): array
    {
        return $this->compile()['params'];
    }

    /**
     * Return the SQL fragment: (SELECT …) AS alias
     *
     * @return string
     */
    public function toSql(): string
    {
        $sql = '(' . $this->compile()['sql'] . ')';
        if ($this->alias !== null) {
            $sql .= " AS {$this->alias}";
        }
        return $sql;
    }

    public function __toString(): string
    {
        return $this->toSql();
    }

    private function compile(): array
    {
        if ($this->compiled !== null) {
            return $this->compiled;
        }

        // sql() populates the subquery params (including lazy When params)
        $sql = $this->sub->sql();
        $params = $this->sub->getParams();

        $renamed = [];
        foreach ($params as $key => $val) {
            $newKey = $this->uid . '_' . $key;
            $sql = preg_replace('/:' . preg_quote($key, '/') . '\b/', ':' . $newKey, $sql);
            $renamed[$newKey] = $val;
        }

        $this->compiled = ['sql' => $sql, 'params' => $renamed];
        return $this->compiled;
    }
}
