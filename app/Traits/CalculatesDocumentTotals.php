<?php

namespace App\Traits;

trait CalculatesDocumentTotals
{
    /**
     * Calculate document totals from line items, discounts, and taxes.
     */
    public function calculateDocumentTotals(array $items, ?string $discountType = null, float|int|string $discountVal = 0, array $taxes = []): array
    {
        $subTotal = 0;
        $totalTax = 0;

        foreach ($items as $item) {
            $qty = isset($item['quantity']) ? (float) $item['quantity'] : 1;
            $price = isset($item['price']) ? (float) $item['price'] : 0;
            $itemSubtotal = $qty * $price;

            $itemDiscount = 0;
            if (isset($item['discount_type'])) {
                if ($item['discount_type'] === 'percentage') {
                    $rate = isset($item['discount']) ? (float) $item['discount'] : 0;
                    $itemDiscount = ($itemSubtotal * $rate) / 100;
                } elseif ($item['discount_type'] === 'fixed') {
                    $itemDiscount = isset($item['discount_val']) ? (float) $item['discount_val'] : (isset($item['discount']) ? (float) $item['discount'] : 0);
                }
            } elseif (isset($item['discount_val']) && (float) $item['discount_val'] > 0) {
                $itemDiscount = (float) $item['discount_val'];
            }

            $lineTotal = max(0, $itemSubtotal - $itemDiscount);
            $subTotal += $lineTotal;

            if (! empty($item['taxes']) && is_array($item['taxes'])) {
                $itemSimpleTax = 0;
                foreach ($item['taxes'] as $tax) {
                    $isCompound = ! empty($tax['compound_tax']);
                    if (! $isCompound) {
                        $taxAmount = isset($tax['percent']) && (float) $tax['percent'] > 0
                            ? ($lineTotal * (float) $tax['percent']) / 100
                            : (isset($tax['amount']) ? max(0, (float) $tax['amount']) : 0);
                        $itemSimpleTax += $taxAmount;
                        $totalTax += $taxAmount;
                    }
                }
                foreach ($item['taxes'] as $tax) {
                    $isCompound = ! empty($tax['compound_tax']);
                    if ($isCompound) {
                        $taxAmount = isset($tax['percent']) && (float) $tax['percent'] > 0
                            ? (($lineTotal + $itemSimpleTax) * (float) $tax['percent']) / 100
                            : (isset($tax['amount']) ? max(0, (float) $tax['amount']) : 0);
                        $totalTax += $taxAmount;
                    }
                }
            }
        }

        $discount = 0;
        if ($discountType === 'percentage') {
            $discount = ($subTotal * (float) $discountVal) / 100;
        } elseif ($discountType === 'fixed') {
            $discount = (float) $discountVal;
        }

        $taxableSubtotal = max(0, $subTotal - $discount);

        if (! empty($taxes) && is_array($taxes)) {
            $docSimpleTax = 0;
            foreach ($taxes as $tax) {
                $isCompound = ! empty($tax['compound_tax']);
                if (! $isCompound) {
                    $taxAmount = isset($tax['percent']) && (float) $tax['percent'] > 0
                        ? ($taxableSubtotal * (float) $tax['percent']) / 100
                        : (isset($tax['amount']) ? max(0, (float) $tax['amount']) : 0);
                    $docSimpleTax += $taxAmount;
                    $totalTax += $taxAmount;
                }
            }
            foreach ($taxes as $tax) {
                $isCompound = ! empty($tax['compound_tax']);
                if ($isCompound) {
                    $taxAmount = isset($tax['percent']) && (float) $tax['percent'] > 0
                        ? (($taxableSubtotal + $docSimpleTax) * (float) $tax['percent']) / 100
                        : (isset($tax['amount']) ? max(0, (float) $tax['amount']) : 0);
                    $totalTax += $taxAmount;
                }
            }
        }

        $total = max(0, $subTotal - $discount + $totalTax);

        return [
            'sub_total' => (int) round($subTotal),
            'discount' => (float) $discount,
            'tax' => (int) round($totalTax),
            'total' => (int) round($total),
        ];
    }
}
