<?php
/**
 * Formatting the payslip templates share.
 *
 * These lived in payslips.php, which meant a preview of a template — drawn
 * without running that page — had no way to call them. One definition, both
 * callers. Both are guarded, so including this twice is harmless.
 */

/** Indian digit grouping: 1234567 → 12,34,567 */
if (!function_exists('indian_number')) {
    function indian_number($value)
    {
        $value = (int) round((float) $value);
        $sign  = $value < 0 ? '-' : '';
        $s     = (string) abs($value);
    
        if (strlen($s) <= 3) {
            return $sign . $s;
        }
    
        $last3 = substr($s, -3);
        $rest  = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', substr($s, 0, -3));
    
        return $sign . $rest . ',' . $last3;
    }
}

if (!function_exists('amt')) {
    function amt($value)
    {
        return $value === null ? '-' : indian_number($value);
    }
}

// Guarded: the framework defines the same helper when this page is served
// through its authenticated route, and redeclaring it would be fatal.
if (!function_exists('e')) {
    function e($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

/** 0–99 in words */
if (!function_exists('words_two')) {
    function words_two($n)
    {
        static $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine',
                        'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen',
                        'Seventeen', 'Eighteen', 'Nineteen'];
        static $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
    
        if ($n < 20) {
            return $ones[$n];
        }
    
        $unit = $n % 10;
        return $tens[intdiv($n, 10)] . ($unit ? '-' . $ones[$unit] : '');
    }
}

if (!function_exists('words_three')) {
    function words_three($n)
    {
        $parts    = [];
        $hundreds = intdiv($n, 100);
        $rest     = $n % 100;
    
        if ($hundreds) {
            $parts[] = words_two($hundreds) . ' Hundred';
        }
        if ($rest) {
            $parts[] = words_two($rest);
        }
    
        return implode(' ', $parts);
    }
}

if (!function_exists('amount_in_words')) {
    function amount_in_words($amount)
    {
        $n = (int) round((float) $amount);
    
        if ($n === 0) {
            return 'Zero Rupees only';
        }
    
        $prefix = $n < 0 ? 'Minus ' : '';
        $n      = abs($n);
        $parts  = [];
    
        $crore = intdiv($n, 10000000);
        $n %= 10000000;
        $lakh = intdiv($n, 100000);
        $n %= 100000;
        $thousand = intdiv($n, 1000);
        $n %= 1000;
    
        if ($crore) {
            $parts[] = words_three($crore) . ' Crore';
        }
        if ($lakh) {
            $parts[] = words_two($lakh) . ' Lakh';
        }
        if ($thousand) {
            $parts[] = words_two($thousand) . ' Thousand';
        }
        if ($n) {
            $parts[] = words_three($n);
        }
    
        return $prefix . implode(' ', $parts) . ' Rupees only';
    }
}
