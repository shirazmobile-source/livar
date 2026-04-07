<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

final class InvoiceNumber
{
    public static function generate(string $table, string $column, string $prefix): string
    {
        $pdo = Database::connection();
        $datePart = date('Ymd');
        $sequencePrefix = $prefix . '-' . $datePart . '-';

        $statement = $pdo->prepare("SELECT {$column} FROM {$table} WHERE {$column} LIKE :prefix ORDER BY id DESC LIMIT 1");
        $statement->execute(['prefix' => $sequencePrefix . '%']);
        $lastNumber = $statement->fetchColumn();

        $sequence = 1;

        if (is_string($lastNumber) && preg_match('/(\d{4})$/', $lastNumber, $matches)) {
            $sequence = ((int) $matches[1]) + 1;
        }

        return sprintf('%s%s-%04d', $prefix . '-', $datePart, $sequence);
    }
}
