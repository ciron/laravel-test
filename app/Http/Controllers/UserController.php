<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Carbon\Carbon;

class UserController extends Controller
{
    public function create(Request $request)
    {

        $request->validate([
            'name' => 'required|string',
            'account_type' => 'required|in:Individual,Business',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
        ]);

        $user = User::create([
            'name' => $request->name,
            'account_type' => $request->account_type,
            'email' => $request->email,
            'password' => bcrypt($request->password),
        ]);

        return response()->json($user);
    }

    public function login(Request $request)
    {

        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $credentials = $request->only('email', 'password');

        if (Auth::attempt($credentials)) {

            $user = Auth::user();
            return response()->json($user);
        }


        return response()->json(['error' => 'Unauthorized'], 401);
    }

    public function showTransactionsAndBalance(Request $request)
    {
        $userId = $request->user()->id;

        $transactions = Transaction::where('user_id', $userId)->get();
        $balance = User::findOrFail($userId)->balance;

        return response()->json([
            'transactions' => $transactions,
            'balance' => $balance,
        ]);
    }

    public function showDeposits(Request $request)
    {
        $userId = $request->user()->id;

        $deposits = Transaction::where('user_id', $userId)
            ->where('transaction_type', 'deposit')
            ->get();

        return response()->json($deposits);
    }

    public function deposit(Request $request)
    {

        $request->validate([
            'user_id' => 'required|exists:users,id',
            'amount' => 'required|numeric|min:0',
        ]);

        $user = User::findOrFail($request->user_id);
        $user->balance += $request->amount;
        $user->save();

        $transaction = Transaction::create([
            'user_id' => $user->id,
            'transaction_type' => 'deposit',
            'amount' => $request->amount,
            'fee' => 0,
            'date' => Carbon::now(),
        ]);

        return response()->json($transaction);
    }

    public function showWithdrawals(Request $request)
    {
        $userId = $request->user()->id;

        $withdrawals = Transaction::where('user_id', $userId)
            ->where('transaction_type', 'withdrawal')
            ->get();

        return response()->json($withdrawals);
    }

    public function withdraw(Request $request)
    {

        $request->validate([
            'user_id' => 'required|exists:users,id',
            'amount' => 'required|numeric|min:0',
        ]);

        $user = User::findOrFail($request->user_id);


        $fee = $this->calculateWithdrawalFee($request->amount, $user->account_type);


        $amountAfterFee = $request->amount - $fee;


        if ($this->isFreeFridayWithdrawal() || $this->isFirst1KFree($request->amount) || $this->isFirst5KFreeThisMonth($request->user_id)) {
            $fee = 0;
            $amountAfterFee = $request->amount;
        }


        if ($this->isBusinessAccountAndExceed50KWithdrawal($request->user_id)) {
            $fee = 0.015 * $request->amount;
            $amountAfterFee = $request->amount - $fee;
        }

        if ($user->balance < $amountAfterFee) {
            return response()->json(['error' => 'Insufficient balance'], 400);
        }

        $user->balance -= $amountAfterFee;
        $user->save();

        $transaction = Transaction::create([
            'user_id' => $user->id,
            'transaction_type' => 'withdrawal',
            'amount' => $request->amount,
            'fee' => $fee,
            'date' => Carbon::now(),
        ]);

        return response()->json($transaction);
    }

    private function calculateWithdrawalFee($amount, $accountType)
    {
        if ($accountType === 'Individual') {
            return 0.015 * $amount;
        } elseif ($accountType === 'Business') {
            return 0.025 * $amount;
        }
    }

    private function isFreeFridayWithdrawal()
    {
        return Carbon::now()->isFriday();
    }

    private function isFirst1KFree($amount)
    {
        return $amount <= 1000;
    }

    private function isFirst5KFreeThisMonth($userId)
    {
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();

        $totalWithdrawalThisMonth = Transaction::where('user_id', $userId)
            ->where('transaction_type', 'withdrawal')
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->sum('amount');

        return $totalWithdrawalThisMonth <= 5000;
    }

    private function isBusinessAccountAndExceed50KWithdrawal($userId)
    {
        $totalWithdrawal = Transaction::where('user_id', $userId)
            ->where('transaction_type', 'withdrawal')
            ->sum('amount');

        $user = User::findOrFail($userId);

        return $user->account_type === 'Business' && $totalWithdrawal > 50000;
    }
}
