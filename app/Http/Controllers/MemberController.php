<?php

namespace App\Http\Controllers;

use App\Models\Members;
use App\Models\PaymentDetails;
use Carbon\Carbon;
use Illuminate\Http\Request;

class MemberController extends Controller
{
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
            'phone' => 'nullable|string|max:15',
            'address' => 'nullable|string|max:255',
            'plan' => 'nullable|string|max:255',
            'age' => 'nullable|integer|min:0',
            'status' => 'nullable|string|in:active,inactive',
            'join_date' => 'nullable|date',
            'payment_status' => 'nullable|string|in:paid,due,overdue',
            'image' => 'nullable|image|max:2048',
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
        $paymentDetail->user_id = auth()->user()->id; // Assuming the user is authenticated
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
}
