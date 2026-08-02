<?php

declare(strict_types=1);

namespace Core;

use Closure;
use Core\Validation\ValidationException;
use Core\Validation\ValidationResult;

/**
 * Validate 1 mang du lieu phang theo rule string kieu Laravel ("required|email|max:255").
 * Thuan tuy - khong Container, khong Config, khong Database, khong biet gi ve HTTP. Registry
 * rule la Closure, la STATE CUA TUNG INSTANCE (khong static/global) - extend() tren 1 instance
 * khong anh huong instance khac.
 *
 * Callback rule co dang (mixed $value, list<string> $params, array<string,mixed> $data,
 * string $field): bool. extend() CHO PHEP ghi de rule built-in (dang ky lai cung ten se thay
 * the callback cu).
 */
final class Validator
{
    /** @var array<string, Closure(mixed, list<string>, array<string,mixed>, string): bool> */
    private array $rules = [];

    public function __construct()
    {
        $this->registerBuiltInRules();
    }

    public function extend(string $ruleName, Closure $callback): void
    {
        $this->rules[$ruleName] = $callback;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $rules 'field' => 'required|email|max:255'
     * @param array<string, string> $messages key dang 'field.rule'
     */
    public function validate(array $data, array $rules, array $messages = []): ValidationResult
    {
        $errors = [];

        foreach ($rules as $field => $ruleString) {
            $parsedRules = $this->parseRuleString($ruleString);
            $ruleNames = \array_column($parsedRules, 'name');

            $exists = \array_key_exists($field, $data);
            $value = $data[$field] ?? null;

            if (!$exists && !\in_array('required', $ruleNames, true)) {
                continue;
            }

            if ($exists && $value === null && \in_array('nullable', $ruleNames, true)) {
                continue;
            }

            foreach ($parsedRules as $rule) {
                $name = $rule['name'];

                if ($name === 'nullable') {
                    continue;
                }

                if (!isset($this->rules[$name])) {
                    throw ValidationException::unknownRule($name);
                }

                $passes = $this->rules[$name]($value, $rule['params'], $data, $field);

                if (!$passes) {
                    $errors[$field][] = $this->resolveMessage($messages, $field, $name, $rule['params']);
                }
            }
        }

        return new ValidationResult($errors);
    }

    /** @return list<array{name: string, params: list<string>}> */
    private function parseRuleString(string $ruleString): array
    {
        $parsed = [];

        foreach (\explode('|', $ruleString) as $rule) {
            $rule = \trim($rule);

            if ($rule === '') {
                continue;
            }

            [$name, $paramString] = \array_pad(\explode(':', $rule, 2), 2, null);

            if ($paramString === null) {
                $params = [];
            } elseif ($name === 'regex') {
                $params = [$paramString];
            } else {
                $params = \explode(',', $paramString);
            }

            $parsed[] = ['name' => $name, 'params' => $params];
        }

        return $parsed;
    }

    /** @param list<string> $params */
    private function resolveMessage(array $messages, string $field, string $rule, array $params): string
    {
        return $messages["{$field}.{$rule}"] ?? $this->defaultMessage($field, $rule, $params);
    }

    /** @param list<string> $params */
    private function defaultMessage(string $field, string $rule, array $params): string
    {
        return match ($rule) {
            'required' => \sprintf('%s la bat buoc.', $field),
            'string' => \sprintf('%s phai la chuoi ky tu.', $field),
            'int', 'integer' => \sprintf('%s phai la so nguyen.', $field),
            'numeric' => \sprintf('%s phai la so.', $field),
            'boolean' => \sprintf('%s phai la gia tri boolean.', $field),
            'array' => \sprintf('%s phai la mang.', $field),
            'email' => \sprintf('%s phai la email hop le.', $field),
            'min' => \sprintf('%s phai co gia tri toi thieu %s.', $field, $params[0] ?? ''),
            'max' => \sprintf('%s khong duoc vuot qua %s.', $field, $params[0] ?? ''),
            'between' => \sprintf('%s phai nam trong khoang %s - %s.', $field, $params[0] ?? '', $params[1] ?? ''),
            'in' => \sprintf('%s khong nam trong danh sach cho phep.', $field),
            'regex' => \sprintf('%s khong dung dinh dang.', $field),
            'date' => \sprintf('%s phai la ngay hop le.', $field),
            'confirmed' => \sprintf('%s xac nhan khong khop.', $field),
            default => \sprintf('%s khong hop le.', $field),
        };
    }

    private function registerBuiltInRules(): void
    {
        $this->extend(
            'required',
            static fn (mixed $value): bool => $value !== null && $value !== '' && $value !== []
        );

        $this->extend('nullable', static fn (): bool => true);

        $this->extend('string', static fn (mixed $value): bool => \is_string($value));

        $this->extend(
            'int',
            static fn (mixed $value): bool => \filter_var($value, FILTER_VALIDATE_INT) !== false
        );

        $this->extend(
            'integer',
            static fn (mixed $value): bool => \filter_var($value, FILTER_VALIDATE_INT) !== false
        );

        $this->extend('numeric', static fn (mixed $value): bool => \is_numeric($value));

        $this->extend(
            'boolean',
            static fn (mixed $value): bool => \in_array($value, [true, false, 0, 1, '0', '1'], true)
        );

        $this->extend('array', static fn (mixed $value): bool => \is_array($value));

        $this->extend(
            'email',
            static fn (mixed $value): bool => \is_string($value) && \filter_var($value, FILTER_VALIDATE_EMAIL) !== false
        );

        $this->extend(
            'min',
            fn (mixed $value, array $params): bool => $this->sizeOf($value) >= (float) ($params[0] ?? 0)
        );

        $this->extend(
            'max',
            fn (mixed $value, array $params): bool => $this->sizeOf($value) <= (float) ($params[0] ?? 0)
        );

        $this->extend('between', function (mixed $value, array $params): bool {
            $size = $this->sizeOf($value);

            return $size >= (float) ($params[0] ?? 0) && $size <= (float) ($params[1] ?? 0);
        });

        $this->extend(
            'in',
            static fn (mixed $value, array $params): bool => \is_scalar($value) && \in_array((string) $value, $params, true)
        );

        $this->extend('regex', static function (mixed $value, array $params): bool {
            return \is_string($value) && isset($params[0]) && \preg_match($params[0], $value) === 1;
        });

        $this->extend(
            'date',
            static fn (mixed $value): bool => \is_string($value) && \strtotime($value) !== false
        );

        $this->extend('confirmed', static function (mixed $value, array $params, array $data, string $field): bool {
            return \array_key_exists("{$field}_confirmation", $data) && $value === $data["{$field}_confirmation"];
        });
    }

    private function sizeOf(mixed $value): float
    {
        if (\is_array($value)) {
            return (float) \count($value);
        }

        if (\is_numeric($value)) {
            return (float) $value;
        }

        return (float) \mb_strlen((string) $value);
    }
}
