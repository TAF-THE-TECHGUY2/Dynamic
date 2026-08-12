<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class CustomerController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Customer::class);

        $search = $request->string('search')->toString();

        $customers = Customer::query()
            ->withCount('documents')
            ->withSum('documents as billed_cents', 'total_cents')
            ->when($search !== '', fn ($query) => $query->where(
                fn ($q) => $q->where('company', 'ilike', "%{$search}%")
                    ->orWhere('contact_name', 'ilike', "%{$search}%")
                    ->orWhere('phone', 'ilike', "%{$search}%"),
            ))
            ->orderBy('company')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('admin/customers/index', [
            'customers' => $customers->through(fn (Customer $customer) => [
                'id' => $customer->id,
                'company' => $customer->company,
                'contact_name' => $customer->contact_name,
                'phone' => $customer->phone,
                'email' => $customer->email,
                'vat_number' => $customer->vat_number,
                'delivery_address' => $customer->delivery_address,
                'notes' => $customer->notes,
                'documents_count' => $customer->documents_count,
                'billed_display' => Money::format((int) ($customer->billed_cents ?? 0)),
            ]),
            'filters' => ['search' => $search],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Customer::class);

        Customer::query()->create($this->validated($request));

        return back()->with('success', 'Customer saved.');
    }

    public function update(Request $request, Customer $customer): RedirectResponse
    {
        $this->authorize('update', $customer);

        $customer->update($this->validated($request));

        // Deliberately no back-fill onto existing documents. A customer renaming
        // their company must not rewrite the address on an invoice already
        // issued — those carry their own snapshot.
        return back()->with('success', 'Customer updated. Documents already issued are unchanged.');
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        $this->authorize('delete', $customer);

        $customer->delete();

        return back()->with('success', 'Customer deleted. Their documents are still on file.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'company' => ['required', 'string', 'max:160'],
            'contact_name' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:160'],
            'vat_number' => ['nullable', 'string', 'max:40'],
            'delivery_address' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
