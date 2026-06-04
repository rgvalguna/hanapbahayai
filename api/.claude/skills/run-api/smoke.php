<?php
/**
 * Direct-invocation smoke driver for the HanapBahay API domain logic.
 *
 * The Laravel app in api/ is an incomplete skeleton (no artisan, no public/,
 * no vendor/, partial config/) and cannot boot — and PHP here has no ext-redis,
 * which composer.json hard-requires. But the *domain math* in
 * app/Modules/Financial is pure PHP (final classes, static methods, native
 * types, no Eloquent/framework) and is the layer most PRs touch. This script
 * autoloads those classes with a tiny PSR-4 shim and exercises them — no
 * database, no Redis, no framework.
 *
 * Run:   php api/.claude/skills/run-api/smoke.php
 * Exit:  0 = all checks pass, 1 = one or more failed.
 */

// ── minimal PSR-4 autoloader: App\  ->  api/app/ (shared with the one-liner) ──
require __DIR__ . '/_autoload.php';
$appDir = realpath(__DIR__ . '/../../../app'); // api/app — for the banner only

use App\Modules\Financial\Amortization;
use App\Modules\Financial\PagIBIG;
use App\Modules\Financial\DTIEngine;
use App\Modules\Financial\BankFinancing;
use App\Modules\Financial\HiddenCosts;

// ── tiny assertion harness ──────────────────────────────────────────────────
$pass = 0; $fail = 0;
function check(string $name, bool $cond, string $detail = ''): void {
    global $pass, $fail;
    if ($cond) { $pass++; echo "  PASS  $name\n"; }
    else       { $fail++; echo "  FAIL  $name" . ($detail ? "  ($detail)" : '') . "\n"; }
}
function near(float $a, float $b, float $tol): bool { return abs($a - $b) <= $tol; }

echo "App\\ dir: $appDir\n";
echo "PHP " . PHP_VERSION . "\n\n";

// ── Amortization ─────────────────────────────────────────────────────────────
echo "Amortization\n";
$pmt = Amortization::monthlyPayment(1_000_000, 6.0, 240);  // 1M @ 6% / 20yr ≈ 7164.31
check('monthlyPayment 1M/6%/20yr ≈ 7164', near($pmt, 7164.31, 1.0), "got $pmt");
check('zero-interest splits evenly', Amortization::monthlyPayment(120_000, 0, 12) === 10000.0);
$max = Amortization::maxAffordableLoan($pmt, 6.0, 240);
check('maxAffordableLoan is inverse of PMT', near($max, 1_000_000, 5.0), "got $max");
$sched = Amortization::schedule(1_000_000, 6.0, 240);
check('schedule has 240 rows ending at 0 balance',
    count($sched) === 240 && $sched[239]['balance'] === 0.0,
    'last balance ' . $sched[239]['balance']);

// ── Pag-IBIG ─────────────────────────────────────────────────────────────────
echo "\nPagIBIG\n";
$elig = PagIBIG::simulate(3_000_000, 80_000, 35, true, 36, 0, 30);
check('eligible member gets a loan', $elig['is_eligible'] === true);
check('loan capped at 90% LTV (2.7M)', near($elig['loan_amount'], 2_700_000, 0.5), 'got ' . $elig['loan_amount']);
check('rate tier for 2.7M is 8.0%', $elig['applicable_rate_pct'] === 8.0, 'got ' . $elig['applicable_rate_pct']);
$noMember = PagIBIG::simulate(3_000_000, 80_000, 35, false, 36);
check('non-member is ineligible', $noMember['is_eligible'] === false && $noMember['ineligibility_reason'] !== null);
$tooFew = PagIBIG::simulate(3_000_000, 80_000, 35, true, 12);
check('<24 contributions is ineligible', $tooFew['is_eligible'] === false);

// ── DTI ──────────────────────────────────────────────────────────────────────
echo "\nDTIEngine\n";
// Mirrors the /financial screenshot: ₱20,671 payment on ₱60k income ≈ 34% -> caution
$dti = DTIEngine::evaluate(20_671, 0, 60_000, 6.5, 240);
check('DTI ratio ≈ 0.3445', near($dti['ratio'], 0.3445, 0.001), 'got ' . $dti['ratio']);
check('DTI status is caution', $dti['status'] === 'caution', 'got ' . $dti['status']);
$crit = DTIEngine::evaluate(40_000, 10_000, 60_000, 6.5, 240);
check('high DTI is critical', $crit['status'] === 'critical', 'got ' . $crit['status']);

// ── Bank financing ───────────────────────────────────────────────────────────
echo "\nBankFinancing\n";
$bank = BankFinancing::simulatePreset(3_000_000, 20, 'bpi');
check('bpi preset: teaser monthly > 0', $bank['teaser_monthly'] > 0, 'got ' . $bank['teaser_monthly']);
check('repriced > teaser monthly', $bank['repriced_monthly'] > $bank['teaser_monthly']);
check('total cost exceeds principal', $bank['total_cost'] > 3_000_000);
$threw = false;
try { BankFinancing::simulatePreset(1_000_000, 20, 'nonexistent'); }
catch (\InvalidArgumentException $e) { $threw = true; }
check('unknown bank preset throws', $threw);

// ── Hidden costs ─────────────────────────────────────────────────────────────
echo "\nHiddenCosts\n";
$hc = HiddenCosts::breakdown(3_500_000, 2_800_000, true, false);
check('DST = 1.5% of loan (42000)', near($hc['dst'], 42_000, 0.5), 'got ' . $hc['dst']);
check('total > 0 and includes moving default', $hc['total'] > 0 && $hc['moving'] === 25_000.0);

// ── summary ──────────────────────────────────────────────────────────────────
echo "\n" . str_repeat('─', 50) . "\n";
echo "Result: $pass passed, $fail failed\n";
exit($fail === 0 ? 0 : 1);
