<?php

namespace Crater\Http\Controllers\V1\Admin\Dashboard;

use Carbon\Carbon;
use Crater\Http\Controllers\Controller;
use Crater\Models\Company;
use Crater\Models\CompanySetting;
use Crater\Models\Customer;
use Crater\Models\Estimate;
use Crater\Models\Expense;
use Crater\Models\Invoice;
use Crater\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Silber\Bouncer\BouncerFacade;

class DashboardController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $company = Company::find($request->header('company'));

        $this->authorize('view dashboard', $company);

        $invoice_totals = [];
        $expense_totals = [];
        $receipt_totals = [];
        $net_income_totals = [];
        $months = [];

        $fiscalYear = CompanySetting::getSetting('fiscal_year', $request->header('company')) ?? '1-12';
        $startDate = Carbon::now();
        $terms = explode('-', $fiscalYear);
        $fiscalYearStartMonth = (int) $terms[0];

        if ($fiscalYearStartMonth <= $startDate->month) {
            $startDate->month($fiscalYearStartMonth)->startOfMonth();
        } else {
            $startDate->subYear()->month($fiscalYearStartMonth)->startOfMonth();
        }

        if ($request->has('previous_year')) {
            $startDate->subYear()->startOfMonth();
        }

        $rangeStart = $startDate->format('Y-m-d');
        $rangeEnd = $startDate->copy()->addMonths(11)->endOfMonth()->format('Y-m-d');

        $invoiceByMonth = $this->monthlySumByColumn(Invoice::query(), 'invoice_date', 'base_total', $rangeStart, $rangeEnd);
        $expenseByMonth = $this->monthlySumByColumn(Expense::query(), 'expense_date', 'base_amount', $rangeStart, $rangeEnd);
        $receiptByMonth = $this->monthlySumByColumn(Payment::query(), 'payment_date', 'base_amount', $rangeStart, $rangeEnd);

        $cursor = $startDate->copy();
        for ($monthCounter = 0; $monthCounter < 12; $monthCounter++) {
            $ym = $cursor->format('Y-m');
            $invoiceSum = (int) ($invoiceByMonth[$ym] ?? 0);
            $expenseSum = (int) ($expenseByMonth[$ym] ?? 0);
            $receiptSum = (int) ($receiptByMonth[$ym] ?? 0);

            $invoice_totals[] = $invoiceSum;
            $expense_totals[] = $expenseSum;
            $receipt_totals[] = $receiptSum;
            $net_income_totals[] = $receiptSum - $expenseSum;
            $months[] = $cursor->format('M');

            $cursor->addMonth()->startOfMonth();
        }

        $total_sales = array_sum($invoice_totals);
        $total_receipts = array_sum($receipt_totals);
        $total_expenses = array_sum($expense_totals);
        $total_net_income = (int) $total_receipts - (int) $total_expenses;

        $chart_data = [
            'months' => $months,
            'invoice_totals' => $invoice_totals,
            'expense_totals' => $expense_totals,
            'receipt_totals' => $receipt_totals,
            'net_income_totals' => $net_income_totals,
        ];

        $total_customer_count = Customer::whereCompany()->count();
        $total_invoice_count = Invoice::whereCompany()
            ->count();
        $total_estimate_count = Estimate::whereCompany()->count();
        $total_amount_due = Invoice::whereCompany()
            ->sum('base_due_amount');

        $recent_due_invoices = Invoice::with('customer')
            ->whereCompany()
            ->where('base_due_amount', '>', 0)
            ->take(5)
            ->latest()
            ->get();
        $recent_estimates = Estimate::with('customer')->whereCompany()->take(5)->latest()->get();

        return response()->json([
            'total_amount_due' => $total_amount_due,
            'total_customer_count' => $total_customer_count,
            'total_invoice_count' => $total_invoice_count,
            'total_estimate_count' => $total_estimate_count,

            'recent_due_invoices' => BouncerFacade::can('view-invoice', Invoice::class) ? $recent_due_invoices : [],
            'recent_estimates' => BouncerFacade::can('view-estimate', Estimate::class) ? $recent_estimates : [],

            'chart_data' => $chart_data,

            'total_sales' => $total_sales,
            'total_receipts' => $total_receipts,
            'total_expenses' => $total_expenses,
            'total_net_income' => $total_net_income,
        ]);
    }

    /**
     * Aggregate a 12-month company-scoped metric grouped by YYYY-MM in one SQL query.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @return array<string, mixed>
     */
    private function monthlySumByColumn($query, string $dateColumn, string $sumColumn, string $rangeStart, string $rangeEnd): array
    {
        $bucketExpr = $this->monthBucketExpression($dateColumn);
        $allowedSumColumns = ['base_total', 'base_amount'];
        if (! in_array($sumColumn, $allowedSumColumns, true)) {
            throw new InvalidArgumentException("Unsupported sum column: {$sumColumn}");
        }

        // Raw SQL required: Eloquent has no portable cross-driver YYYY-MM date-bucket grouping builder.
        return $query
            ->whereBetween($dateColumn, [$rangeStart, $rangeEnd])
            ->whereCompany()
            ->selectRaw("{$bucketExpr} as ym, SUM({$sumColumn}) as total")
            ->groupByRaw($bucketExpr)
            ->pluck('total', 'ym')
            ->all();
    }

    private function monthBucketExpression(string $dateColumn): string
    {
        $allowedDateColumns = ['invoice_date', 'payment_date', 'expense_date'];
        if (! in_array($dateColumn, $allowedDateColumns, true)) {
            throw new InvalidArgumentException("Unsupported date column: {$dateColumn}");
        }

        return match (DB::getDriverName()) {
            'sqlite' => "strftime('%Y-%m', {$dateColumn})",
            'pgsql' => "to_char({$dateColumn}, 'YYYY-MM')",
            default => "DATE_FORMAT({$dateColumn}, '%Y-%m')",
        };
    }
}
