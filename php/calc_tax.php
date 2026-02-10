    <?php
// Simple tax calculator endpoint
// POST: salary (numeric)
// Returns JSON: { status: 'success', tax: 0.00 }
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status'=>'error','message'=>'Invalid method']);
    exit;
}

$salary = floatval($_POST['salary'] ?? 0);

// NOTE: Update the brackets below to match the current Nepal government tax slabs.
// The array $brackets should be an ordered list of [upper_limit, rate], where rate is decimal (e.g., 0.1 for 10%).
// Upper limits are annual salary limits. For progressive tax, we compute tax on portions.

$brackets = [
    // example placeholder brackets (annual) — PLEASE ADJUST to the legal rates for Nepal
    [500000, 0.01],    // up to 500,000: 1%
    [700000, 0.10],    // next up to 700,000: 10%
    [1500000, 0.20],   // next up to 1,500,000: 20%
    [3000000, 0.30],   // next up to 3,000,000: 30%
    [PHP_FLOAT_MAX, 0.36] // above: 36%
];

// If salary appears to be monthly (small value), assume monthly -> convert to annual for calculation
// Heuristic: if salary < 10000, treat as monthly? We'll use explicit value: assume input is monthly if <= 200000
$isMonthly = false;
if ($salary > 0 && $salary <= 200000) {
    // likely monthly salary — convert to annual
    $annual = $salary * 12;
    $isMonthly = true;
} else {
    $annual = $salary;
}

$remaining = $annual;
$prev_limit = 0;
$tax = 0.0;
foreach ($brackets as $b) {
    $limit = $b[0];
    $rate = $b[1];
    if ($annual > $prev_limit) {
        $taxable = min($annual, $limit) - $prev_limit;
        if ($taxable > 0) {
            $tax += $taxable * $rate;
        }
    }
    $prev_limit = $limit;
    if ($annual <= $limit) break;
}

// Convert back to monthly if input was monthly
if ($isMonthly) {
    $tax_out = $tax / 12.0;
} else {
    $tax_out = $tax;
}

echo json_encode(['status'=>'success','tax'=>round($tax_out,2),'annual_tax'=>round($tax,2),'isMonthly'=>$isMonthly]);
