<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use PDO;

final class DashboardController extends Controller
{
    public function index(): void
    {
        $this->requirePermission('dashboard');

        $pdo = Database::connection();

        $stats = [
            'today_sales' => (float) ($pdo->query('SELECT COALESCE(SUM(COALESCE(final_amount_aed, final_amount)), 0) FROM sales WHERE invoice_date = CURDATE()')->fetchColumn() ?: 0),
            'today_purchases' => (float) ($pdo->query('SELECT COALESCE(SUM(COALESCE(final_amount_aed, final_amount)), 0) FROM purchases WHERE invoice_date = CURDATE()')->fetchColumn() ?: 0),
            'customers' => (int) ($pdo->query('SELECT COUNT(*) FROM customers WHERE status = "active"')->fetchColumn() ?: 0),
            'low_stock' => (int) ($pdo->query('SELECT COUNT(*) FROM products WHERE status = "active" AND current_stock <= min_stock')->fetchColumn() ?: 0),
            'products' => (int) ($pdo->query('SELECT COUNT(*) FROM products WHERE status = "active"')->fetchColumn() ?: 0),
            'monthly_profit' => (float) ($pdo->query('
                SELECT COALESCE(SUM((COALESCE(unit_price_aed, unit_price) - cost_price) * qty), 0)
                FROM sale_items si
                INNER JOIN sales s ON s.id = si.sale_id
                WHERE s.invoice_date BETWEEN DATE_FORMAT(CURDATE(), "%Y-%m-01") AND CURDATE()
            ')->fetchColumn() ?: 0),
        ];

        $recentSales = $pdo->query('
            SELECT s.id, s.invoice_no, s.invoice_date, s.final_amount, s.final_amount_aed, s.currency_code, s.payment_status, c.name AS customer_name
            FROM sales s
            LEFT JOIN customers c ON c.id = s.customer_id
            ORDER BY s.id DESC
            LIMIT 6
        ')->fetchAll(PDO::FETCH_ASSOC);

        $recentPurchases = $pdo->query('
            SELECT p.id, p.invoice_no, p.invoice_date, p.final_amount, p.final_amount_aed, p.currency_code, sp.name AS supplier_name
            FROM purchases p
            LEFT JOIN suppliers sp ON sp.id = p.supplier_id
            ORDER BY p.id DESC
            LIMIT 6
        ')->fetchAll(PDO::FETCH_ASSOC);

        $topProducts = $pdo->query('
            SELECT pr.name, SUM(si.qty) AS sold_qty, SUM(COALESCE(si.total_price_aed, si.total_price)) AS sold_value
            FROM sale_items si
            INNER JOIN products pr ON pr.id = si.product_id
            INNER JOIN sales s ON s.id = si.sale_id
            WHERE s.invoice_date BETWEEN DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND CURDATE()
            GROUP BY pr.id, pr.name
            ORDER BY sold_qty DESC, sold_value DESC
            LIMIT 5
        ')->fetchAll(PDO::FETCH_ASSOC);

        $this->render('dashboard/index', [
            'title' => 'Dashboard',
            'stats' => $stats,
            'recentSales' => $recentSales,
            'recentPurchases' => $recentPurchases,
            'topProducts' => $topProducts,
        ]);
    }
}
