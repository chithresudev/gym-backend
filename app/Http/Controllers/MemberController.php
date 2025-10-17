<?php

namespace App\Http\Controllers;

use App\Models\Members;
use App\Models\Organization;
use App\Models\PaymentDetails;
use Carbon\Carbon;
use Illuminate\Http\Request;

class MemberController extends Controller
{

    public function getRegisterCode()
    {
        // Determine organization from header
        $orgName = request()->header('Organization');
        $org = $orgName ? Organization::where('organization_name', $orgName)->first() : null;
        $org_shortcode = $org ? strtoupper(substr(preg_replace('/\s+/', '', $org->organization_name), 0, 3)) : 'REG';

        // Find the highest existing code for this org matching SHORTCODEdddd
        $max = 0;
        if ($org) {
            $codes = Members::where('organization_id', $org->id)
                ->where('register_no', 'like', $org_shortcode . '%')
                ->pluck('register_no');

            foreach ($codes as $code) {
                if (preg_match('/^' . preg_quote($org_shortcode, '/') . '([0-9]{4})$/', $code, $m)) {
                    $n = (int)$m[1];
                    if ($n > $max) $max = $n;
                }
            }
        } else {
            // Fallback: consider all members if org not provided
            $codes = Members::where('register_no', 'like', $org_shortcode . '%')->pluck('register_no');
            foreach ($codes as $code) {
                if (preg_match('/^' . preg_quote($org_shortcode, '/') . '([0-9]{4})$/', $code, $m)) {
                    $n = (int)$m[1];
                    if ($n > $max) $max = $n;
                }
            }
        }

        $next = $max + 1;
        $nextCode = $org_shortcode . str_pad((string)$next, 4, '0', STR_PAD_LEFT);
        return ['register_code' => $nextCode];
    }
    /**
     * Show the form for creating a new member.
     */
    public function memberRegister(Request $request)
    {

        // Validate request
        $validated = $request->validate([
            'organization_id' => 'required|exists:organizations,id',
            'name' => 'required|string|max:255',
            'register_no' => 'nullable|string|max:50',
            'email' => 'required|email|max:255|unique:members,email',
            'phone' => 'nullable|string|max:10|unique:members,phone',
            'address' => 'nullable|string|max:255',
            'plan' => 'nullable|string|max:255',
            'age' => 'nullable|integer|min:0',
            'status' => 'nullable|string|in:active,inactive',
            'join_date' => 'nullable|date',
            'payment_status' => 'nullable|string|in:paid,due,overdue',
            'image' => 'required|image|max:5000',
        ]);

        // Handle image upload
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('members', 'public');
            $validated['image'] = $imagePath;
        }

        // Set default values if not provided
        $validated['status'] = $request->input('status', 'active');
        $validated['payment_status'] = $request->input('payment_status', 'due');

        // Create the member
        $member = Members::create($validated);
        $this->addPaymentDue($member);

        return response()->json([
            'message' => 'Member created successfully',
            'member' => $member
        ], 201);
    }

    /**
     * Public (unauthenticated) member registration via QR.
     */
    public function publicMemberRegister(Request $request)
    {
        // Same validation as memberRegister
        $validated = $request->validate([
            'organization_id' => 'required|exists:organizations,id',
            'name' => 'required|string|max:255',
            'register_no' => 'nullable|string|max:50',
            'email' => 'required|email|max:255|unique:members,email',
            'phone' => 'nullable|string|max:10|unique:members,phone',
            'address' => 'nullable|string|max:255',
            'plan' => 'nullable|string|max:255',
            'age' => 'nullable|integer|min:0',
            'status' => 'nullable|string|in:active,inactive',
            'join_date' => 'nullable|date',
            'payment_status' => 'nullable|string|in:paid,due,overdue',
            'image' => 'required|image|max:5000',
        ]);

        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('members', 'public');
            $validated['image'] = $imagePath;
        }

        $validated['status'] = $request->input('status', 'active');
        $validated['payment_status'] = $request->input('payment_status', 'due');

        $member = Members::create($validated);
        $this->addPaymentDue($member);

        return response()->json([
            'message' => 'Member created successfully (public)',
            'member' => $member
        ], 201);
    }



    /**
     * Display a listing of members.
     */
    public function showMembers(Request $request)
    {

        $filter = $request->query('filter', 'all');

        $query = Members::query();

        switch ($filter) {
            case 'active':
                $query->where('status', 'active');
                break;

            case 'disabled':
                $query->where('status', '!=', 'active');
                break;

            case 'unpaid':
                $query->whereHas('paymentDetails', function ($q) {
                    $q->where('status', 'unpaid');
                });
                break;

            case 'all':
            default:
                break;
        }

        return response()->json($query->get());
        // Logic to retrieve and return a list of members
        $members = Members::get();
        return response()->json($members);
    }
    /**
     * Display a listing of paymentDetails.
     */
    public function paymentDetails(Members $member)
    {
        $payments = $member->paymentDetails;
        if ($payments->isEmpty()) {
            return response()->json(['message' => 'No payment details found for this member'], 404);
        }
        return response()->json($member, 200);
    }

    /**
     * payment everymonth check a listing of paymentDetails.
     */
    public function checkPaymentDue()
    {
        $members = Members::get();

        foreach ($members as $member) {
            if (count($member->paymentDetails)) {
                $payments = collect($member->paymentDetails)->sortByDesc('created_at')->first();
                $diff_next_days = Carbon::parse($payments->created_at)->diffInDays(Carbon::parse($payments->created_at)->addMonth(1));
                $diff_days = Carbon::parse($payments->created_at)->diffInDays(Carbon::now());

                if ($diff_days >= $diff_next_days) {
                    $this->addPaymentDue($member);
                }
            }
        }

        return response()->json([
            'message' => 'Payment due check completed'
        ]);
    }


    /**
     * Display the specified member.
     */
    private function addPaymentDue($member)
    {
        // Create the payment detail
        $paymentDetail = new PaymentDetails();
        $paymentDetail->user_id = auth()->check() ? auth()->user()->id : null; // Support unauthenticated flows
        $paymentDetail->member_id = $member->id;

        if ($member['plan'] == 'prime') {
            $amount = 2000;
        } else if ($member['plan'] == 'middle') {
            $amount = 1200;
        } else {
            $amount = 800;
        }
        $paymentDetail->amount = $amount;
        $paymentDetail->status = 'unpaid'; // Assuming the payment is being marked as paid

        $paymentDetail->save();
    }
    /**
     * Display the specified member.
     */
    public function paidPayment(PaymentDetails $payment, Request $request)
    {

        // Logic to validate and add payment details for a member
        $validated = $request->validate([
            'payment_method' => 'required|string|max:50',
            'remarks' => 'nullable|string|max:255',
        ]);

        // Create the payment detail
        $payment->user_id = auth()->user()->id; // Assuming the user is authenticated
        $payment->payment_method = $validated['payment_method'];
        $payment->remarks = $validated['remarks'] ?? null;
        $payment->status = 'paid'; // Assuming the payment is being marked as paid
        $payment->paid_at = Carbon::now();
        $payment->save();

        return response()->json(['message' => 'Payment details added successfully'], 201);
    }
    /**
     * Show the form for editing the specified member.
     */
    public function changeMemberStatus(Members $member)
    {
        // Logic to change the status of a member

        $member->status = $member->status === 'active' ? 'inactive' : 'active';
        $member->save();

        return response()->json(['message' => 'Member status updated successfully', 'member' => $member], 200);
    }
    /**
     * Return paid members within the last N days for the current organization.
     */
    public function paidMembers(Request $request)
    {
        $days = (int) $request->query('days', 7);
        $days = $days > 0 ? $days : 7;

        $orgName = $request->header('Organization');
        $org = Organization::where('organization_name', $orgName)->first();

        $query = PaymentDetails::query()
            ->join('members', 'members.id', '=', 'payment_details.member_id')
            ->when($org, function ($q) use ($org) {
                return $q->where('members.organization_id', $org->id);
            })
            ->where('payment_details.status', 'paid')
            ->where('payment_details.paid_at', '>=', Carbon::now()->subDays($days))
            ->orderBy('payment_details.paid_at', 'desc')
            ->select(
                'payment_details.id',
                'payment_details.member_id',
                'members.name',
                'members.image as image',
                'payment_details.amount',
                'payment_details.paid_at'
            );

        $rows = $query->get()->map(function ($r) {
            $base = request()->getSchemeAndHttpHost();
            $r->image_url = $r->image ? $base . '/storage/' . $r->image : null;
            if ($r->paid_at) {
                $r->paid_at_iso = is_string($r->paid_at)
                    ? Carbon::parse($r->paid_at)->toIso8601String()
                    : $r->paid_at->toIso8601String();
            } else {
                $r->paid_at_iso = null;
            }
            return $r;
        });

        return response()->json($rows, 200);
    }

    /**
     * Return newly created members within the last N days (with image URLs) for the current organization.
     */
    public function newMembers(Request $request)
    {
        $days = (int) $request->query('days', 7);
        $days = $days > 0 ? $days : 7;

        $orgName = $request->header('Organization');
        $org = Organization::where('organization_name', $orgName)->first();

        $query = Members::query()
            ->when($org, function ($q) use ($org) {
                return $q->where('organization_id', $org->id);
            })
            ->where('created_at', '>=', Carbon::now()->subDays($days))
            ->orderBy('created_at', 'desc')
            ->select('id', 'name', 'image', 'created_at');

        $members = $query->get()->map(function ($m) {
            $base = request()->getSchemeAndHttpHost();
            $m->image_url = $m->image ? $base . '/storage/' . $m->image : null;
            if ($m->created_at) {
                $m->joined_at = is_string($m->created_at)
                    ? Carbon::parse($m->created_at)->toIso8601String()
                    : $m->created_at->toIso8601String();
            } else {
                $m->joined_at = null;
            }
            return $m;
        });

        return response()->json($members, 200);
    }

    /**
     * Show the form for editing the specified member.
     */
    public function dashboardData()
    {
        // Logic to change the status of a member
        $monthStart = Carbon::today()->startOfMonth();
        $monthEnd = Carbon::today()->endOfMonth();
        $monthLastStart = Carbon::today()->subMonth()->startOfMonth();
        $monthLastEnd = Carbon::today()->subMonth()->endOfMonth();
        $thisYearStart = Carbon::today()->startOfYear();
        $thisYearEnd = Carbon::today()->endOfYear();

        $lastYearStart = Carbon::today()->subYear()->startOfYear();
        $lastYearEnd = Carbon::today()->subYear()->endOfYear();

        $totalPayments = PaymentDetails::sum('amount');

        $totalMembers = Members::count();
        $activeMembers = Members::where('status', 'active')->count();
        $inactiveMembers = Members::where('status', 'inactive')->count();
        $unpaidPayments = PaymentDetails::where('status', 'unpaid')->sum('amount');

        $thisMonthPayments = PaymentDetails::whereBetween('paid_at', [$monthStart, $monthEnd])->sum('amount');
        $lastMonthPayments = PaymentDetails::whereBetween('paid_at', [$monthLastStart, $monthLastEnd])->sum('amount');
        $thisYearPayments = PaymentDetails::whereBetween('paid_at', [$thisYearStart, $thisYearEnd])->sum('amount');
        $lastYearPayments = PaymentDetails::whereBetween('paid_at', [$lastYearStart, $lastYearEnd])->sum('amount');


        $query = Members::query();
        $unpaidMembers = $query->whereHas('paymentDetails', function ($q) {
            $q->where('status', 'unpaid');
        })->with(['paymentDetails' => function ($q) {
            $q->where('status', 'unpaid')->orderByDesc('created_at');
        }])->get()->map(function ($m) {
            $base = request()->getSchemeAndHttpHost();
            $m->image_url = $m->image ? $base . '/storage/' . $m->image : null;
            $lastUnpaid = $m->paymentDetails->first();
            if ($lastUnpaid && $lastUnpaid->created_at) {
                $m->unpaid_at = is_string($lastUnpaid->created_at)
                    ? Carbon::parse($lastUnpaid->created_at)->toIso8601String()
                    : $lastUnpaid->created_at->toIso8601String();
            } else {
                $m->unpaid_at = null;
            }
            if ($m->created_at) {
                $m->joined_at = is_string($m->created_at)
                    ? Carbon::parse($m->created_at)->toIso8601String()
                    : $m->created_at->toIso8601String();
            } else {
                $m->joined_at = null;
            }
            // Reduce payload size if needed
            unset($m->paymentDetails);
            return $m;
        });

        $lastWeekNewMembers = Members::whereBetween('created_at', [Carbon::now()->subWeek(), Carbon::now()])->get();

        return response()->json([
            'total_payments' => $totalPayments,
            'total_members' => $totalMembers,
            'active_members' => $activeMembers,
            'inactive_members' => $inactiveMembers,
            'unpaid_payments' => $unpaidPayments,
            'this_month_payments' => $thisMonthPayments,
            'last_month_payments' => $lastMonthPayments,
            'this_year_payments' => $thisYearPayments,
            'last_year_payments' => $lastYearPayments,
            'unpaid_members' => $unpaidMembers,
            'last_week_new_members' => $lastWeekNewMembers,
        ], 200);
    }
}
 