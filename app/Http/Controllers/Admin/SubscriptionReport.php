<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Models\PlanSubscribe;
use App\Http\Controllers\Controller;

class SubscriptionReport extends Controller
{
    public function index(Request $request)
    {
        $subscribers = PlanSubscribe::with(['plan:id,subscriptionName','business:id,companyName,business_category_id','business.category:id,name'])->latest()->paginate(20);
        return view('admin.subscribers.index', compact('subscribers'));
    }

    public function acnooFilter(Request $request)
    {
        $search = $request->input('search');

        $subscribers = PlanSubscribe::with([
            'plan:id,subscriptionName',
            'business:id,companyName,business_category_id',
            'business.category:id,name'
        ])
            ->when($search, function ($q) use ($search) {
                $q->where(function ($q) use ($search) {
                    $q->where('duration', 'like', '%' . $search . '%')
                        ->orWhereHas('plan', function ($q) use ($search) {
                            $q->where('subscriptionName', 'like', '%' . $search . '%');
                        })
                        ->orWhereHas('gateway', function ($q) use ($search) {
                            $q->where('name', 'like', '%' . $search . '%');
                        })
                        ->orWhereHas('business', function ($q) use ($search) {
                            $q->where('companyName', 'like', '%' . $search . '%')
                                ->orWhereHas('category', function ($q) use ($search) {
                                    $q->where('name', 'like', '%' . $search . '%');
                                });
                        });
                });
            })
            ->latest()
            ->paginate($request->per_page ?? 20);

        if ($request->ajax()) {
            return response()->json([
                'data' => view('admin.subscribers.datas', compact('subscribers'))->render()
            ]);
        }

        return redirect(url()->previous());
    }


    public function reject(Request $request, string $id)
    {

        $request->validate([
            'notes' => 'required|string|max:255',
        ]);

        $reject = PlanSubscribe::findOrFail($id);

        if ($reject) {

            $reject->update([
                'payment_status' => 'unpaid',
                'notes' => $request->notes,
            ]);

            return response()->json([
                'message' => 'Status Unpaid',
                'redirect' => route('admin.subscription-reports.index'),
            ]);
        } else {
            return response()->json(['message' => 'request not found'], 404);
        }
    }

    public function paid(Request $request, string $id)
    {

        $request->validate([
            'notes' => 'required|string|max:255',
        ]);

        $approve = PlanSubscribe::findOrFail($id);

        if ($approve) {

            $approve->update([
                'payment_status' => 'paid',
                'notes' => $request->notes,
            ]);

            return response()->json([
                'message' => 'Status Paid',
                'redirect' => route('admin.subscription-reports.index'),
            ]);
        } else {
            return response()->json(['message' => 'request not found'], 404);
        }
    }
}
