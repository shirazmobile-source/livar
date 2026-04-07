<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use PDO;

final class ReportController extends Controller
{
    public function index(): void
    {
        $this->requirePermission('reports');

        $tab = trim((string) ($_GET['tab'] ?? 'overview'));
        $from = trim((string) ($_GET['from'] ?? date('Y-m-01')));
        $to = trim((string) ($_GET['to'] ?? date('Y-m-d')));

        $pdo = Database::connection();

        $salesAmountExpr = $this->amountExpression($pdo, 'sales', 'final_amount_aed', 'final_amount');
        $purchaseAmountExpr = $this->amountExpression($pdo, 'purchases', 'final_amount_aed', 'final_amount');
        $saleUnitExpr = $this->amountExpression($pdo, 'sale_items', 'unit_price_aed', 'unit_price', 'si');
        $saleTotalExpr = $this->amountExpression($pdo, 'sale_items', 'total_price_aed', 'total_price', 'si');

        $overview = [
            'sales_total' => $this->scalar($pdo, sprintf('SELECT COALESCE(SUM(%s), 0) FROM sales WHERE invoice_date BETWEEN :from AND :to', $salesAmountExpr), compact('from', 'to')),
            'purchase_total' => $this->scalar($pdo, sprintf('SELECT COALESCE(SUM(%s), 0) FROM purchases WHERE invoice_date BETWEEN :from AND :to', $purchaseAmountExpr), compact('from', 'to')),
            'gross_profit' => $this->scalar($pdo, sprintf('
                SELECT COALESCE(SUM((%s - si.cost_price) * si.qty), 0)
                FROM sale_items si
                INNER JOIN sales s ON s.id = si.sale_id
                WHERE s.invoice_date BETWEEN :from AND :to
            ', $saleUnitExpr), compact('from', 'to')),
            'sales_count' => $this->scalar($pdo, 'SELECT COUNT(*) FROM sales WHERE invoice_date BETWEEN :from AND :to', compact('from', 'to')),
            'purchase_count' => $this->scalar($pdo, 'SELECT COUNT(*) FROM purchases WHERE invoice_date BETWEEN :from AND :to', compact('from', 'to')),
        ];

        $salesSummary = $this->fetchAll($pdo, sprintf('
            SELECT invoice_date, COUNT(*) AS invoice_count, SUM(%s) AS total_amount
            FROM sales
            WHERE invoice_date BETWEEN :from AND :to
            GROUP BY invoice_date
            ORDER BY invoice_date DESC
        ', $salesAmountExpr), compact('from', 'to'));

        $purchaseSummary = $this->fetchAll($pdo, sprintf('
            SELECT invoice_date, COUNT(*) AS invoice_count, SUM(%s) AS total_amount
            FROM purchases
            WHERE invoice_date BETWEEN :from AND :to
            GROUP BY invoice_date
            ORDER BY invoice_date DESC
        ', $purchaseAmountExpr), compact('from', 'to'));

        $inventory = $pdo->query('
            SELECT code, name, category, unit, purchase_price, sale_price, current_stock, min_stock,
                CASE WHEN current_stock <= min_stock THEN "Low" ELSE "OK" END AS stock_status
            FROM products
            ORDER BY name ASC
        ')->fetchAll(PDO::FETCH_ASSOC);

        $customerSales = $this->fetchAll($pdo, sprintf('
            SELECT c.name, COUNT(s.id) AS invoice_count, COALESCE(SUM(%s), 0) AS total_amount
            FROM customers c
            LEFT JOIN sales s ON s.customer_id = c.id AND s.invoice_date BETWEEN :from AND :to
            GROUP BY c.id, c.name
            ORDER BY total_amount DESC, c.name ASC
        ', $this->amountExpression($pdo, 'sales', 'final_amount_aed', 'final_amount', 's')), compact('from', 'to'));

        $supplierPurchases = $this->fetchAll($pdo, sprintf('
            SELECT sp.name, COUNT(p.id) AS invoice_count, COALESCE(SUM(%s), 0) AS total_amount
            FROM suppliers sp
            LEFT JOIN purchases p ON p.supplier_id = sp.id AND p.invoice_date BETWEEN :from AND :to
            GROUP BY sp.id, sp.name
            ORDER BY total_amount DESC, sp.name ASC
        ', $this->amountExpression($pdo, 'purchases', 'final_amount_aed', 'final_amount', 'p')), compact('from', 'to'));

        $topProducts = $this->fetchAll($pdo, sprintf('
            SELECT pr.name, SUM(si.qty) AS sold_qty, SUM(%s) AS sold_amount
            FROM sale_items si
            INNER JOIN products pr ON pr.id = si.product_id
            INNER JOIN sales s ON s.id = si.sale_id
            WHERE s.invoice_date BETWEEN :from AND :to
            GROUP BY pr.id, pr.name
            ORDER BY sold_qty DESC, sold_amount DESC
            LIMIT 10
        ', $saleTotalExpr), compact('from', 'to'));

        $stockMovements = $this->fetchAll($pdo, '
            SELECT sm.created_at, pr.name AS product_name, sm.type, sm.qty_in, sm.qty_out, sm.balance_after, sm.note
            FROM stock_movements sm
            INNER JOIN products pr ON pr.id = sm.product_id
            ORDER BY sm.id DESC
            LIMIT 30
        ', []);

        $this->render('reports/index', [
            'title' => 'Setting / Reports',
            'tab' => $tab,
            'from' => $from,
            'to' => $to,
            'overview' => $overview,
            'salesSummary' => $salesSummary,
            'purchaseSummary' => $purchaseSummary,
            'inventory' => $inventory,
            'customerSales' => $customerSales,
            'supplierPurchases' => $supplierPurchases,
            'topProducts' => $topProducts,
            'stockMovements' => $stockMovements,
        ]);
    }

    private function amountExpression(PDO $pdo, string $table, string $preferredColumn, string $fallbackColumn, string $alias = ''): string
    {
        $column = $this->tableHasColumn($pdo, $table, $preferredColumn) ? $preferredColumn : $fallbackColumn;
        $prefix = $alias !== '' ? $alias . '.' : '';

        return $prefix . $column;
    }

    private function tableHasColumn(PDO $pdo, string $table, string $column): bool
    {
        static $cache = [];
        $key = $table . '.' . $column;

        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        $statement = $pdo->prepare(sprintf('SHOW COLUMNS FROM `%s` LIKE :column', $table));
        $statement->execute(['column' => $column]);

        return $cache[$key] = (bool) $statement->fetch(PDO::FETCH_ASSOC);
    }

    private function scalar(PDO $pdo, string $sql, array $params): float
    {
        $statement = $pdo->prepare($sql);
        $statement->execute($params);

        return (float) ($statement->fetchColumn() ?: 0);
    }

    private function fetchAll(PDO $pdo, string $sql, array $params): array
    {
        $statement = $pdo->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
}
