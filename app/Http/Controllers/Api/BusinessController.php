<?php

namespace App\Http\Controllers\Api;

use App\Models\Plan;
use App\Models\User;
use App\Models\Business;
use App\Helpers\HasUploader;
use Illuminate\Http\Request;
use App\Models\PlanSubscribe;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

class BusinessController extends Controller
{
    use HasUploader;

    public function index()
    {
        $user = User::select('id', 'name', 'role', 'visibility', 'lang', 'email')->findOrFail(auth()->id());
        $business = Business::with('category:id,name', 'enrolled_plan:id,plan_id,business_id,price,duration', 'enrolled_plan.plan:id,subscriptionName')->findOrFail(auth()->user()->business_id);

        $data = array_merge($business->toArray(), ['user' => $user->toArray()]);

        return response()->json([
            'message' => __('Data fetched successfully.'),
            'data' => $data
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'address' => 'nullable|max:250',
            'companyName' => 'required|max:250',
            'pictureUrl' => 'nullable|image|max:5120',
            'shopOpeningBalance' => 'nullable|numeric',
            'business_category_id' => 'required|exists:business_categories,id',
            'dtipoRuc' => 'required|in:Natural,Jurídico',
            'druc' => 'required|string',
            'ddv' => 'required|string',
            'dnombEm' => 'required_if:dtipoRuc,Jurídico|string',
            'dcoordEm' => 'nullable|string',
            'ddirecEm' => 'nullable|string',
            'dcorreg' => 'nullable|string',
            'ddistr' => 'nullable|string',
            'dprov' => 'nullable|string',
            'dtfnEm' => 'nullable|string',
            'dcorElectEmi' => 'nullable|email'
        ]);

        DB::beginTransaction();
        try {
            $user = auth()->user();
            $free_plan = Plan::where('subscriptionPrice', '<=', 0)->orWhere('offerPrice', '<=', 0)->first();

            $business = Business::create($request->except(['pictureUrl', 'dtipoRuc', 'druc', 'ddv', 'dnombEm', 'dcoordEm', 'ddirecEm', 'dcodUbi', 'dcorreg', 'ddistr', 'dprov', 'dtfnEm', 'dcorElectEmi']) + [
                'phoneNumber' => $request->phoneNumber,
                'subscriptionDate' => $free_plan ? now() : NULL,
                'will_expire' => now()->addDays($free_plan->duration),
                'pictureUrl' => $request->pictureUrl ? $this->upload($request, 'pictureUrl') : NULL
            ]);

            // Create invoice data
            $request->merge(['dcodUbi' => $request->dcorreg]);
            $business->invoice_data()->create($request->only([
                'dtipoRuc', 'druc', 'ddv', 'dnombEm', 'dcoordEm', 
                'ddirecEm', 'dcodUbi', 'dcorreg', 'ddistr', 'dprov',
                'dtfnEm', 'dcorElectEmi'
            ]));

            $user->update([
                'business_id' => $business->id,
                'phone' => $request->phoneNumber,
                'name' => $business->companyName,
            ]);

            if ($free_plan) {
                $subscribe = PlanSubscribe::create([
                                'plan_id' => $free_plan->id,
                                'business_id' => $business->id,
                                'duration' => $free_plan->duration,
                            ]);

                $business->update([
                    'plan_subscribe_id' => $subscribe->id,
                ]);
            }

            DB::commit();
            return response()->json([
                'message' => __('Business setup completed.'),
            ]);

        } catch (\Throwable $th) {
            DB::rollback();
            return response()->json(__('Something was wrong, Please contact with admin.'), 403);
        }
    }

    public function update(Request $request, Business $business)
    {
        $request->validate([
            'address' => 'nullable|max:250',
            'companyName' => 'required|max:250',
            'pictureUrl' => 'nullable|image|max:5120',
            'business_category_id' => 'required|exists:business_categories,id',
            'phoneNumber'  => ['nullable', 'min:5', 'max:15'],
            'dtipoRuc' => 'required|in:Natural,Jurídico',
            'druc' => 'required|string',
            'ddv' => 'required|string',
            'dnombEm' => 'required_if:dtipoRuc,Jurídico|string',
            'dcoordEm' => 'nullable|string',
            'ddirecEm' => 'nullable|string',
            'dcorreg' => 'nullable|string',
            'ddistr' => 'nullable|string',
            'dprov' => 'nullable|string',
            'dtfnEm' => 'nullable|string',
            'dcorElectEmi' => 'nullable|email'
        ]);

        DB::beginTransaction();
        try {
            auth()->user()->update([
                'name' => $request->companyName,
                'phone' => $request->phoneNumber,
            ]);

            $business->update([
                'address' => $request->address,
                'phoneNumber' => $request->phoneNumber,
                'companyName' => $request->companyName,
                'business_category_id' => $request->business_category_id,
                'pictureUrl' => $request->pictureUrl ? $this->upload($request, 'pictureUrl', $business->pictureUrl) : $business->pictureUrl,
            ]);

            // Update or create invoice data
            $request->merge(['dcodUbi' => $request->dcorreg]);
            $business->invoice_data()->updateOrCreate(
                ['business_id' => $business->id],
                $request->only([
                    'dtipoRuc', 'druc', 'ddv', 'dnombEm', 'dcoordEm', 
                    'ddirecEm', 'dcodUbi', 'dcorreg', 'ddistr', 'dprov',
                    'dtfnEm', 'dcorElectEmi'
                ])
            );

            DB::commit();
            return response()->json([
                'message' => __('Data saved successfully.'),
                'business' => $business->load('invoice_data'),
            ]);
        } catch (\Throwable $th) {
            DB::rollback();
            return response()->json(__('Something went wrong.'), 403);
        }
    }
}
