<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\TokenTopup;
use App\Services\Admin\OrderService;
use App\Services\WalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function __construct(
        private OrderService $orderService,
        private WalletService $walletService,
    ) {}

    public function index(Request $request): View
    {
        $tab = $request->query('tab', 'all');

        $data = $this->orderService->indexData($tab);
        $data['tokenTopups'] = $this->walletService->pendingAndRecentForAdmin(20);

        return view('admin.orders.index', $data);
    }

    public function show(Request $request, int $order): View
    {
        return view('admin.orders.show', $this->orderService->showData($order));
    }

    public function approve(Request $request, Order $order): RedirectResponse
    {
        $this->orderService->approve(Auth::user(), $order);

        return redirect()->route('admin.orders.show', $order->id)->with('status', 'order-approved');
    }

    public function reject(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $this->orderService->reject(Auth::user(), $order, $data['reason']);

        return redirect()->route('admin.orders.show', $order->id)->with('status', 'order-rejected');
    }

    public function approveTopup(Request $request, TokenTopup $tokenTopup): RedirectResponse
    {
        try {
            $this->walletService->approveTopup(Auth::user(), $tokenTopup);
        } catch (ValidationException $e) {
            return redirect()->route('admin.orders.index')->withErrors($e->errors());
        }

        return redirect()->route('admin.orders.index')->with('status', 'topup-approved');
    }

    public function rejectTopup(Request $request, TokenTopup $tokenTopup): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        try {
            $this->walletService->rejectTopup(Auth::user(), $tokenTopup, $data['reason']);
        } catch (ValidationException $e) {
            return redirect()->route('admin.orders.index')->withErrors($e->errors());
        }

        return redirect()->route('admin.orders.index')->with('status', 'topup-rejected');
    }
}
