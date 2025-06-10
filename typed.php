<?php

class VariableNotFoundException extends InvalidArgumentException {}
class TypeMismatchException extends InvalidArgumentException {}

class Variable
{
    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public mixed $value = null
    ) {}
}

/**
 * VariableManager is a singleton class that manages variables with type checking.
 * It allows setting, getting, updating, and unsetting variables with type constraints.
 */
class VariableManager {
    /** @var array<string, Variable> */
    private static array $variables = [];
    private static ?self $instance = null;

    private function __construct() {}

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __clone() {}

    // Added for testability
    public static function reset(): void
    {
        self::$variables = [];
        self::$instance = null;
    }

    private function normalizeType(?string $type): ?string
    {
        if ($type === null) {
            return null;
        }

        // Only normalize primitive types and common aliases.
        // Complex types (nullable, union, intersection, generics) are NOT normalized here.
        // They are handled directly in valueTypeMatchesTheSpecifiedType.
        return match (strtolower($type)) {
            'int', 'integer' => 'int',
            'bool', 'boolean' => 'bool',
            'float', 'double' => 'float',
            'string' => 'string',
            'array' => 'array',
            'object' => 'object',
            'null' => 'null',
            'mixed' => 'mixed',
            'resource' => 'resource',
            'closure' => 'closure',
            default => $type, // For custom classes, interfaces, traits, and all complex types
        };
    }

    // New helper function to parse complex type definitions into an AST
    private function parseTypeExpression(string $typeString): array
    {
        $tokens = $this->tokenizeTypeString($typeString);
        $parser = new TypeExpressionParser($tokens);
        return $parser->parse();
    }

    // Helper to tokenize the type string
    private function tokenizeTypeString(string $typeString): array
    {
        // Split by delimiters, keeping the delimiters, and filter out empty strings
        // This regex ensures that type names (words) and delimiters are captured correctly.
        // Added \\ for namespace support in class names.
        preg_match_all('/([a-zA-Z0-9_\\\\]+|[|&()<>?,])/', $typeString, $matches);
        return array_values(array_filter($matches[0], fn($token) => trim($token) !== ''));
    }

    private function valueTypeMatchesTheSpecifiedType($value, ?string $type): bool
    {
        if ($type === null || $type === 'mixed') {
            return true;
        }

        $currentType = $type; // Use a local variable for parsing

        // 1. Handle nullable types
        $isNullable = false;
        if (str_starts_with($currentType, "?")) {
            $isNullable = true;
            $currentType = substr($currentType, 1);
        }
        if ($isNullable && $value === null) {
            return true;
        }
        if ($value === null) { // If not nullable and value is null, it's a mismatch
            return false;
        }

        // Parse the type string into an AST
        $ast = $this->parseTypeExpression($currentType);

        // Evaluate the AST
        return $this->evaluateTypeAst($value, $ast);
    }

    private function evaluateTypeAst($value, array $astNode): bool
    {
        $nodeType = $astNode[0];
        switch ($nodeType) {
            case 'type':
                $simpleTypeName = $astNode[1];
                $normalizedSimpleType = $this->normalizeType($simpleTypeName);
                return match ($normalizedSimpleType) {
                    'int' => is_int($value),
                    'bool' => is_bool($value),
                    'float' => is_float($value),
                    'string' => is_string($value),
                    'array' => is_array($value),
                    'object' => is_object($value),
                    'null' => is_null($value),
                    'resource' => is_resource($value),
                    'closure' => $value instanceof Closure,
                    default => is_object($value) && is_a($value, $simpleTypeName, true),
                };
            case 'union':
                $left = $astNode[1];
                $right = $astNode[2];
                return $this->evaluateTypeAst($value, $left) || $this->evaluateTypeAst($value, $right);
            case 'intersection':
                $left = $astNode[1];
                $right = $astNode[2];
                return $this->evaluateTypeAst($value, $left) && $this->evaluateTypeAst($value, $right);
            case 'generic':
                $baseType = $astNode[1];
                $genericArgsAsts = $astNode[2]; // This will be an array of AST nodes for generic arguments

                if (strtolower($baseType) === 'array') {
                    if (!is_array($value)) {
                        return false;
                    }
                    // For array generics, we expect 2 arguments: key type and value type
                    if (count($genericArgsAsts) !== 2) {
                        // Handle cases like array<T> where only one generic argument is provided
                        // If only one argument, assume it's the value type and key type is mixed
                        if (count($genericArgsAsts) === 1) {
                            $valueTypeAst = $genericArgsAsts[0];
                            return array_all($value, fn($val) => $this->evaluateTypeAst($val, $valueTypeAst));
                        } else {
                            return false; // Invalid generic array type (e.g., array<T,U,V>)
                        }
                    }
                    $keyTypeAst = $genericArgsAsts[0];
                    $valueTypeAst = $genericArgsAsts[1];

                    foreach ($value as $key => $val) {
                        if (!$this->evaluateTypeAst($key, $keyTypeAst)) {
                            return false;
                        }
                        if (!$this->evaluateTypeAst($val, $valueTypeAst)) {
                            return false;
                        }
                    }
                } else {
                    // For other generic classes, validate the base class
                    if (!is_object($value) || !is_a($value, $baseType, true)) {
                        return false;
                    }
                    // Further validation of generic arguments for custom classes is complex
                    // and out of scope for this simple parser without reflection capabilities
                    // to inspect generic types at runtime.
                }
                return true;
            default:
                return false;
        }
    }

    private function throwIfValueTypeDoesNotMatchTheSpecifiedType($value, ?string $type): void
    {
        if (!$this->valueTypeMatchesTheSpecifiedType($value, $type)) {
            throw new TypeMismatchException("Value type does not match the specified type '$type'.");
        }
    }

    private function throwIfNotIsset(string $name): void
    {
        if (!$this->isset($name)) {
            throw new VariableNotFoundException("Variable with name '$name' does not exist.");
        }
    }

    private function throwIfIsset(string $name): void
    {
        if ($this->isset($name)) {
            throw new InvalidArgumentException("Variable with name '$name' already exists.");
        }
    }

    private function isset(string $name): bool
    {
        return isset(self::$variables[$name]);
    }

    public function set(): mixed
    {
        $params = func_get_args();

        if (count($params) < 2) {
            throw new InvalidArgumentException('At least two parameters are required: name and value.');
        }

        if (count($params) > 3) {
            throw new InvalidArgumentException('Too many parameters provided. Only name, type, and value are allowed.');
        }

        $name = $params[0];
        $value = $params[1];
        $type = $params[2] ?? null;

        // Infer type if isn't provided and it's a new variable
        if (!$this->isset($name) && $type === null) {
            $type = get_debug_type($value);
        }

        if ($this->isset($name)) {
            return $this->update($name, $value);
        }

        return $this->create($name, $value, $type);
    }

    public function get(string $name): mixed
    {
        $this->throwIfNotIsset($name);
        return self::$variables[$name]->value;
    }

    private function create(string $name, $value = null, ?string $type = null): mixed
    {
        $this->throwIfValueTypeDoesNotMatchTheSpecifiedType($value, $type);
        self::$variables[$name] = new Variable($name, $type, $value);

        return $this->get($name);
    }

    private function update(string $name, $value): mixed
    {
        $this->throwIfNotIsset($name);
        $variable = self::$variables[$name];
        $this->throwIfValueTypeDoesNotMatchTheSpecifiedType($value, $variable->type);
        $variable->value = $value;
        return $this->get($name);
    }

    public function unset(string $name): void
    {
        $this->throwIfNotIsset($name);
        unset(self::$variables[$name]);
    }
}

// A simple recursive descent parser for type expressions
class TypeExpressionParser
{
    private array $tokens;
    private int $position = 0;

    public function __construct(array $tokens)
    {
        $this->tokens = $tokens;
    }

    public function parse(): array
    {
        $result = $this->parseUnion();
        if ($this->peek() !== null) {
            throw new InvalidArgumentException("Unexpected token at end of expression: " . $this->peek());
        }
        return $result; // Always return an array (AST)
    }

    private function parseUnion(): array
    {
        $left = $this->parseIntersection();
        while ($this->peek() === '|') {
            $this->consume(); // Consume '|'
            $right = $this->parseIntersection();
            $left = ['union', $left, $right]; // Represent union as ['union', left_node, right_node]
        }
        return $left;
    }

    private function parseIntersection(): array
    {
        $left = $this->parsePrimary();
        while ($this->peek() === '&') {
            $this->consume(); // Consume '&'
            $right = $this->parsePrimary();
            $left = ['intersection', $left, $right]; // Represent intersection as ['intersection', left_node, right_node]
        }
        return $left;
    }

    private function parsePrimary(): array
    {
        $token = $this->peek();
        if ($token === '(') {
            $this->consume(); // Consume '('
            $expression = $this->parseUnion();
            if ($this->peek() !== ')') {
                throw new InvalidArgumentException("Mismatched parentheses: Expected ')'");
            }
            $this->consume(); // Consume ')'
            return $expression; // Return the inner expression (which is already an array AST)
        } elseif ($token !== null) {
            $baseType = $this->consume(); // Consume the base type (e.g., 'array', 'string', 'MyClass')

            // Check for generic arguments
            if ($this->peek() === '<') {
                $this->consume(); // Consume '<'
                $genericArgs = [];
                while ($this->peek() !== '>') {
                    if ($this->peek() === null) {
                        throw new InvalidArgumentException("Unclosed generic type: Expected '>' ");
                    }
                    $genericArgs[] = $this->parseUnion(); // Parse each generic argument
                    if ($this->peek() === ',') {
                        $this->consume(); // Consume ','
                    }
                }
                $this->consume(); // Consume '>'
                return ['generic', $baseType, $genericArgs]; // Represent generic as ['generic', base_type, [arg1_ast, arg2_ast]]
            }
            return ['type', $baseType]; // Represent simple type as ['type', type_name]
        }
        throw new InvalidArgumentException("Unexpected end of type expression or invalid token: " . ($token ?? 'null'));
    }

    private function peek(): ?string
    {
        return $this->tokens[$this->position] ?? null;
    }

    private function consume(): string
    {
        if (!isset($this->tokens[$this->position])) {
            throw new InvalidArgumentException("Unexpected end of tokens.");
        }
        return $this->tokens[$this->position++];
    }
}

function typed(): mixed
{
    $args = func_get_args();
    $instance = VariableManager::getInstance();
    return match (count($args)) {
        0 => $instance,
        1 => $instance->get($args[0]),
        2 => $instance->set($args[0], $args[1]),
        3 => $instance->set($args[0], $args[1], $args[2]),
        default => throw new InvalidArgumentException('Invalid number of arguments.'),
    };
}
