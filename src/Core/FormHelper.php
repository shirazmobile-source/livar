<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class FormHelper
{
    public static function normalizeItems(array $productIds, array $quantities, array $unitPrices, array $pricingUnits = [], array $unitsPerBoxes = []): array
    {
        $items = [];

        foreach ($productIds as $index => $productId) {
            $productId = (int) $productId;
            $displayQty = isset($quantities[$index]) ? (float) $quantities[$index] : 0;
            $displayUnitPrice = isset($unitPrices[$index]) ? (float) $unitPrices[$index] : 0;
            $pricingUnit = strtolower(trim((string) ($pricingUnits[$index] ?? 'unit')));
            $unitsPerBox = isset($unitsPerBoxes[$index]) ? (float) $unitsPerBoxes[$index] : 1.0;
            $unitsPerBox = $unitsPerBox > 0 ? $unitsPerBox : 1.0;

            if ($productId <= 0 && $displayQty <= 0 && $displayUnitPrice <= 0) {
                continue;
            }

            if ($productId <= 0 || $displayQty <= 0 || $displayUnitPrice < 0) {
                throw new RuntimeException('Each line item requires a product, quantity, and unit price.');
            }

            if ($pricingUnit !== 'box') {
                $pricingUnit = 'unit';
                $unitsPerBox = 1.0;
            }

            if ($pricingUnit === 'box' && $unitsPerBox <= 1.0) {
                throw new RuntimeException('The selected product is not configured with a valid Units per Box value.');
            }

            $baseQty = $pricingUnit === 'box' ? $displayQty * $unitsPerBox : $displayQty;
            $baseUnitPrice = $pricingUnit === 'box'
                ? ($unitsPerBox > 0 ? $displayUnitPrice / $unitsPerBox : $displayUnitPrice)
                : $displayUnitPrice;

            $items[] = [
                'product_id' => $productId,
                'display_qty' => round($displayQty, 2),
                'pricing_unit' => $pricingUnit,
                'units_per_box' => round($unitsPerBox, 2),
                'qty' => round($baseQty, 2),
                'unit_price' => round($baseUnitPrice, 2),
                'total_price' => round($displayQty * $displayUnitPrice, 2),
            ];
        }

        if ($items === []) {
            throw new RuntimeException('Please add at least one line item.');
        }

        return $items;
    }

    public static function applyCurrencyRate(array $items, float $rateToAed): array
    {
        if ($rateToAed <= 0) {
            throw new RuntimeException('A valid currency rate is required.');
        }

        foreach ($items as $index => $item) {
            $unitPriceAed = round((float) $item['unit_price'] * $rateToAed, 2);
            $items[$index]['unit_price_aed'] = $unitPriceAed;
            $items[$index]['total_price'] = round((float) ($item['total_price'] ?? 0), 2);
            $items[$index]['total_price_aed'] = round((float) ($item['total_price'] ?? 0) * $rateToAed, 2);
        }

        return $items;
    }

    public static function computeTotals(array $items, float $discountAmount = 0): array
    {
        $totalAmount = 0.0;

        foreach ($items as $item) {
            $totalAmount += (float) $item['total_price'];
        }

        $discountAmount = max(0, $discountAmount);
        $finalAmount = max(0, $totalAmount - $discountAmount);

        return [
            'total_amount' => round($totalAmount, 2),
            'discount_amount' => round($discountAmount, 2),
            'final_amount' => round($finalAmount, 2),
        ];
    }

    public static function computeTotalsWithRate(array $items, float $discountAmount = 0, float $rateToAed = 1.0): array
    {
        $invoiceTotals = self::computeTotals($items, $discountAmount);
        $totalAmountAed = 0.0;

        foreach ($items as $item) {
            $totalAmountAed += (float) ($item['total_price_aed'] ?? 0);
        }

        $discountAmountAed = max(0, round($discountAmount * $rateToAed, 2));
        $finalAmountAed = max(0, round($totalAmountAed - $discountAmountAed, 2));

        return $invoiceTotals + [
            'total_amount_aed' => round($totalAmountAed, 2),
            'discount_amount_aed' => $discountAmountAed,
            'final_amount_aed' => $finalAmountAed,
        ];
    }
}
