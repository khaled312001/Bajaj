<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Support\Activity;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Admin: product catalog (price list, warranty, guarantor requirements). */
class ProductController extends Controller
{
    public function index()
    {
        return view('products.index', ['products' => Product::orderBy('sort')->orderBy('id')->get()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $p = Product::create($data + ['sort' => (int) Product::max('sort') + 1]);
        Activity::log('settings', $p, 'إضافة منتج ' . $p->name . ' بسعر ' . number_format($p->price, 2));

        return back()->with('success', 'تمت إضافة المنتج.');
    }

    public function update(Request $request, Product $product)
    {
        $product->update($this->validated($request, $product));
        Activity::log('settings', $product, 'تعديل المنتج ' . $product->name . ' (السعر ' . number_format($product->price, 2) . ')');

        return back()->with('success', 'تم تحديث المنتج.');
    }

    public function destroy(Product $product)
    {
        $product->delete();
        Activity::log('settings', $product, 'حذف المنتج ' . $product->name);

        return back()->with('success', 'تم حذف المنتج.');
    }

    private function validated(Request $request, ?Product $product = null): array
    {
        $d = $request->validate([
            'name' => ['required', 'string', 'max:190', Rule::unique('products', 'name')->ignore($product?->id)],
            'price' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'warranty' => ['nullable', 'string', 'max:190'],
            'requirements' => ['nullable', 'string', 'max:1000'],
        ], ['name.unique' => 'اسم المنتج موجود بالفعل.']);
        $d['name'] = trim($d['name']);
        $d['is_active'] = $request->has('is_active') ? $request->boolean('is_active') : true;

        return $d;
    }
}
