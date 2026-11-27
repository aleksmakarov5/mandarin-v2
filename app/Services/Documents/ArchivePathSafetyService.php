<?php

namespace App\Services\Documents;

use RuntimeException;

class ArchivePathSafetyService
{
    public function normalize(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $path = trim($path);

        if (
            $path === ''
            || str_contains($path, "\0")
            || str_starts_with($path, '/')
            || preg_match('/^[A-Za-z]:\//', $path)
        ) {
            throw new RuntimeException(
                'Архив содержит недопустимый путь.'
            );
        }

        $parts = [];

        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }

            if ($part === '..') {
                throw new RuntimeException(
                    'Архив содержит выход за пределы каталога.'
                );
            }

            if (preg_match('/[\x00-\x1F\x7F]/', $part)) {
                throw new RuntimeException(
                    'Архив содержит управляющие символы в имени.'
                );
            }

            $parts[] = $part;
        }

        if (!$parts) {
            throw new RuntimeException(
                'Пустой путь внутри архива.'
            );
        }

        $result = implode('/', $parts);

        if (
            mb_strlen($result) > config(
                'documents.archives.max_path_length',
                500
            )
        ) {
            throw new RuntimeException(
                'Путь внутри архива слишком длинный.'
            );
        }

        return $result;
    }

    public function depth(string $path): int
    {
        return substr_count($path, '/') + 1;
    }
}