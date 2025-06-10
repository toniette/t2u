<?php

// Exceptions
class VariableNotFoundException extends InvalidArgumentException {}
class TypeMismatchException extends InvalidArgumentException {}
class InvalidTypeExpressionException extends InvalidArgumentException {}

// Value Object for Variable
class Variable
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $type,
        public mixed $value = null
    ) {}
}

// Type System - Responsible for parsing and evaluating type expressions
interface TypeSystemInterface
{
    public function parse(string $typeString): array;
    public function evaluate(mixed $value, array $astNode): bool;
}

class TypeSystem implements TypeSystemInterface
{
    public function parse(string $typeString): array
    {
        $tokens = $this->tokenizeTypeString($typeString);
        $parser = new TypeExpressionParser($tokens);
        return $parser->parse();
    }

    public function evaluate(mixed $value, array $astNode): bool
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
                return $this->evaluate($value, $left) || $this->evaluate($value, $right);
            case 'intersection':
                $left = $astNode[1];
                $right = $astNode[2];
                return $this->evaluate($value, $left) && $this->evaluate($value, $right);
            case 'generic':
                $baseType = $astNode[1];
                $genericArgsAsts = $astNode[2];

                if (strtolower($baseType) === 'array') {
                    if (!is_array($value)) {
                        return false;
                    }
                    if (count($genericArgsAsts) === 1) {
                        $valueTypeAst = $genericArgsAsts[0];
                        return array_all($value, fn($val) => $this->evaluate($val, $valueTypeAst));
                    } elseif (count($genericArgsAsts) === 2) {
                        $keyTypeAst = $genericArgsAsts[0];
                        $valueTypeAst = $genericArgsAsts[1];

                        foreach ($value as $key => $val) {
                            if (!$this->evaluate($key, $keyTypeAst)) {
                                return false;
                            }
                            if (!$this->evaluate($val, $valueTypeAst)) {
                                return false;
                            }
                        }
                        return true;
                    }
                    return false; // Invalid generic array type
                } else {
                    if (!is_object($value) || !is_a($value, $baseType, true)) {
                        return false;
                    }
                }
                return true;
            default:
                return false;
        }
    }

    private function tokenizeTypeString(string $typeString): array
    {
        preg_match_all('/([a-zA-Z0-9_\\\\]+|[|&()<>?,])/', $typeString, $matches);
        return array_values(array_filter($matches[0], fn($token) => trim($token) !== ''));
    }

    private function normalizeType(?string $type): ?string
    {
        if ($type === null) {
            return null;
        }
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
            default => $type,
        };
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
            throw new InvalidTypeExpressionException("Unexpected token at end of expression: " . $this->peek());
        }
        return $result;
    }

    private function parseUnion(): array
    {
        $left = $this->parseIntersection();
        while ($this->peek() === '|') {
            $this->consume();
            $right = $this->parseIntersection();
            $left = ['union', $left, $right];
        }
        return $left;
    }

    private function parseIntersection(): array
    {
        $left = $this->parsePrimary();
        while ($this->peek() === '&') {
            $this->consume();
            $right = $this->parsePrimary();
            $left = ['intersection', $left, $right];
        }
        return $left;
    }

    private function parsePrimary(): array
    {
        $token = $this->peek();
        if ($token === '(') {
            $this->consume();
            $expression = $this->parseUnion();
            if ($this->peek() !== ')') {
                throw new InvalidTypeExpressionException("Mismatched parentheses: Expected ')'");
            }
            $this->consume();
            return $expression;
        } elseif ($token !== null) {
            $baseType = $this->consume();

            if ($this->peek() === '<') {
                $this->consume();
                $genericArgs = [];
                while ($this->peek() !== '>') {
                    if ($this->peek() === null) {
                        throw new InvalidTypeExpressionException("Unclosed generic type: Expected '>' ");
                    }
                    $genericArgs[] = $this->parseUnion();
                    if ($this->peek() === ',') {
                        $this->consume();
                    }
                }
                $this->consume();
                return ['generic', $baseType, $genericArgs];
            }
            return ['type', $baseType];
        }
        throw new InvalidTypeExpressionException("Unexpected end of type expression or invalid token: $token");
    }

    private function peek(): ?string
    {
        return $this->tokens[$this->position] ?? null;
    }

    private function consume(): string
    {
        if (!isset($this->tokens[$this->position])) {
            throw new InvalidTypeExpressionException("Unexpected end of tokens.");
        }
        return $this->tokens[$this->position++];
    }
}

// Variable Manager - Manages variables with type checking
class VariableManager
{
    /** @var array<string, Variable> */
    private array $variables = [];
    private TypeSystemInterface $typeSystem;

    public function __construct(?TypeSystemInterface $typeSystem = null)
    {
        $this->typeSystem = $typeSystem ?? new TypeSystem();
    }

    public function set(string $name, mixed $value, ?string $type = null): mixed
    {
        if ($this->exists($name)) {
            return $this->update($name, $value);
        }
        return $this->create($name, $value, $type);
    }

    public function unset(string $name): void
    {
        $this->ensureVariableExists($name);
        unset($this->variables[$name]);
    }

    public function exists(string $name): bool
    {
        return isset($this->variables[$name]);
    }

    private function create(string $name, mixed $value, ?string $type): mixed
    {
        $resolvedType = $type ?? get_debug_type($value);
        $this->ensureValueMatchesType($value, $resolvedType);
        $this->variables[$name] = new Variable($name, $resolvedType, $value);
        return $value;
    }

    private function update(string $name, mixed $value): mixed
    {
        $variable = $this->variables[$name];
        $this->ensureValueMatchesType($value, $variable->type);
        $variable->value = $value;
        return $value;
    }

    private function ensureValueMatchesType(mixed $value, ?string $type): void
    {
        if ($type === null) {
            return;
        }

        $isNullable = str_starts_with($type, "?");
        $actualType = $isNullable ? substr($type, 1) : $type;

        if ($isNullable && $value === null) {
            return;
        }
        if ($value === null) {
            throw new TypeMismatchException("Value cannot be null for non-nullable type '$type'.");
        }

        try {
            $ast = $this->typeSystem->parse($actualType);
            if (!$this->typeSystem->evaluate($value, $ast)) {
                throw new TypeMismatchException("Value type does not match the specified type '$type'.");
            }
        } catch (InvalidTypeExpressionException $e) {
            throw new InvalidTypeExpressionException("Invalid type expression '$type': " . $e->getMessage(), 0, $e);
        }
    }

    private function ensureVariableExists(string $name): void
    {
        if (!$this->exists($name)) {
            throw new VariableNotFoundException("Variable with name '$name' does not exist.");
        }
    }
}
