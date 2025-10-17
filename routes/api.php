<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\OrganizationController;
use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::get('/organization/{organization_name}', [OrganizationController::class, 'searchOrganization']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/register-code', [MemberController::class, 'getRegisterCode']);
Route::post('/public/member-register', [MemberController::class, 'publicMemberRegister']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    Route::get('/dashboard', [MemberController::class, 'dashboardData']);
    Route::get('/paid-members', [MemberController::class, 'paidMembers']);
    Route::get('/new-members', [MemberController::class, 'newMembers']);

    Route::post('/member-register', [MemberController::class, 'memberRegister']);
    Route::get('/member-list', [MemberController::class, 'showMembers']);
    Route::post('/member/status/{member?}', [MemberController::class, 'changeMemberStatus']);

    Route::get('/payment-details/{member?}', [MemberController::class, 'paymentDetails']);
    Route::get('/check-payment-due', [MemberController::class, 'checkPaymentDue']);
    // Route::post('/add-payment/{member?}', [MemberController::class, 'addPayment']);
    Route::post('/member/paid-payment/{payment?}', [MemberController::class, 'paidPayment']);
    Route::get('/organization-list', [OrganizationController::class, 'index']);
    Route::post('/organization-create', [OrganizationController::class, 'create']);
});
 