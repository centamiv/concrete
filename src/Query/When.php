<?php

namespace Concrete\Query;

class When
{
    private array $branches = [];
    private ?string $pendingWhen = null;
    private ?string $elseSql = null;
    private ?string $alias = null;
    private array $params = [];
    private int $counter = 0;
    private string $uid;

    private static int $instances = 0;

    private static array $ALLOWED_OPERATORS = [
        '=', '!=', '<>', '<', '>', '<=', '>=', 'LIKE', 'NOT LIKE', 'IS', 'IS NOT',
    ];

    public function __construct()
    {
        self::$instances++;
        $this->uid = 'ce' . self::$instances;
    }

    public static function make(): self
    {
        return new self();
    }

    /**
     * Add a WHEN condition. Must be followed by then().
     *
     * @param string $col   Table-qualified or bare column name
     * @param string $operator
     * @param mixed  $value Value to compare against (bound as PDO parameter)
     * @return $this
     */
    public function when(string $col, string $operator, $value): self
    {
        if ($this->pendingWhen !== null) {
            throw new \LogicException('Missing then() after previous when()');
        }
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*(\.[a-zA-Z_][a-zA-Z0-9_]*)?$/', $col)) {
            throw new \InvalidArgumentException("Invalid column identifier: '$col'");
        }
        $operator = strtoupper(trim($operator));
        if (!in_array($operator, self::$ALLOWED_OPERATORS, true)) {
            throw new \InvalidArgumentException("Invalid operator: '$operator'");
        }
        $paramName = $this->uid . '_w' . $this->counter++;
        $this->params[$paramName] = $value;
        $this->pendingWhen = "$col $operator :$paramName";
        return $this;
    }

    /**
     * Set the result value for the preceding WHEN condition.
     *
     * @param mixed $result  int/float → embedded literal; string → PDO param; null → NULL literal
     * @return $this
     */
    public function then($result): self
    {
        if ($this->pendingWhen === null) {
            throw new \LogicException('then() must be called after when()');
        }
        $this->branches[] = ['when' => $this->pendingWhen, 'then' => $this->buildResult($result, 't')];
        $this->pendingWhen = null;
        return $this;
    }

    /**
     * Set the ELSE fallback value.
     *
     * @param mixed $result  int/float → embedded literal; string → PDO param; null → NULL literal
     * @return $this
     */
    public function else($result): self
    {
        if ($this->pendingWhen !== null) {
            throw new \LogicException('Missing then() before else()');
        }
        $this->elseSql = $this->buildResult($result, 'e');
        return $this;
    }

    /**
     * Set the column alias for the CASE expression (AS …).
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
     * Return the PDO parameters bound by this expression's WHEN/THEN/ELSE clauses.
     *
     * @return array
     */
    public function getParams(): array
    {
        return $this->params;
    }

    /**
     * Build and return the SQL fragment for this CASE expression.
     *
     * @return string
     */
    public function toSql(): string
    {
        if (empty($this->branches)) {
            throw new \LogicException('CASE expression must have at least one WHEN … THEN branch');
        }
        if ($this->pendingWhen !== null) {
            throw new \LogicException('Incomplete CASE expression: missing then() after when()');
        }

        $sql = 'CASE';
        foreach ($this->branches as $branch) {
            $sql .= " WHEN {$branch['when']} THEN {$branch['then']}";
        }
        if ($this->elseSql !== null) {
            $sql .= " ELSE {$this->elseSql}";
        }
        $sql .= ' END';
        if ($this->alias !== null) {
            $sql .= " AS {$this->alias}";
        }
        return $sql;
    }

    public function __toString(): string
    {
        return $this->toSql();
    }

    private function buildResult($result, string $type): string
    {
        if ($result === null) {
            return 'NULL';
        }
        if (is_int($result) || is_float($result)) {
            return (string) $result;
        }
        $paramName = $this->uid . '_' . $type . $this->counter++;
        $this->params[$paramName] = $result;
        return ':' . $paramName;
    }
}
