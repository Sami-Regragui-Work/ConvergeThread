<?php

namespace App\Support;

use Illuminate\Http\Request;

trait SortsLists
{
    /**
     * Normalize a sort + direction pair against a whitelist of allowed fields.
     *
     * @param  Request|array<mixed>  $query
     * @param  list<string>  $fields
     * @return array{0: string, 1: string}
     */
    protected function resolveSort(
        Request|array $query,
        array $fields,
        string $defaultField,
        string $defaultDir = 'desc',
        string $param = 'sort',
        string $dirParam = 'dir',
    ): array {
        $query = $query instanceof Request ? $query->query() : $query;

        $field = $query[$param] ?? null;

        if (! is_string($field) || ! in_array($field, $fields, true)) {
            $field = $defaultField;
        }

        $dir = strtolower((string) ($query[$dirParam] ?? $defaultDir));

        if (! in_array($dir, ['asc', 'desc'], true)) {
            $dir = $defaultDir;
        }

        return [$field, $dir];
    }
}
