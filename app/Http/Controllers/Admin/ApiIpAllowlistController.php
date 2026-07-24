<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiIpAllowlist;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ApiIpAllowlistController extends Controller
{
    public function index()
    {
        return view('admin.masterdata.api-ip-allowlists.index');
    }

    public function data(Request $request)
    {
        $query = ApiIpAllowlist::query()->with(['creator:id,name', 'updater:id,name'])->orderByDesc('is_active')->orderBy('ip_address');
        if ($q = trim((string) $request->input('q', ''))) {
            $query->where(fn ($x) => $x->where('ip_address', 'like', "%{$q}%")->orWhere('name', 'like', "%{$q}%"));
        }

        $total = ApiIpAllowlist::count();
        $filtered = (clone $query)->count();
        $rows = $query->skip(max(0, (int) $request->input('start', 0)))
            ->take(min(100, max(1, (int) $request->input('length', 10))))
            ->get()
            ->map(fn (ApiIpAllowlist $row) => [
                'id' => $row->id,
                'ip_address' => $row->ip_address,
                'name' => $row->name,
                'note' => $row->note,
                'is_active' => $row->is_active,
                'expires_at' => $row->expires_at?->format('Y-m-d\\TH:i'),
                'created_by_name' => $row->creator?->name,
                'updated_by_name' => $row->updater?->name,
                'updated_at' => $row->updated_at?->format('Y-m-d H:i'),
            ]);

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $total,
            'recordsFiltered' => $filtered,
            'data' => $rows,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $data['created_by'] = auth()->id();
        $data['updated_by'] = auth()->id();
        $allowlist = ApiIpAllowlist::create($data);

        return response()->json(['message' => 'IP allowlist berhasil ditambahkan', 'data' => $allowlist]);
    }

    public function update(Request $request, ApiIpAllowlist $apiIpAllowlist)
    {
        $data = $this->validateData($request, $apiIpAllowlist);
        $data['updated_by'] = auth()->id();
        $apiIpAllowlist->update($data);

        return response()->json(['message' => 'IP allowlist berhasil diperbarui', 'data' => $apiIpAllowlist]);
    }

    public function destroy(ApiIpAllowlist $apiIpAllowlist)
    {
        $apiIpAllowlist->update([
            'is_active' => false,
            'updated_by' => auth()->id(),
        ]);

        return response()->json(['message' => 'IP allowlist dinonaktifkan. Riwayat tetap disimpan.']);
    }

    private function validateData(Request $request, ?ApiIpAllowlist $allowlist = null): array
    {
        $request->merge(['ip_address' => trim((string) $request->input('ip_address'))]);
        $data = $request->validate([
            'ip_address' => [
                'required',
                'string',
                'max:50',
                Rule::unique('api_ip_allowlists', 'ip_address')->ignore($allowlist?->id),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! $this->isValidIpOrCidr((string) $value)) {
                        $fail('IP harus berupa alamat IPv4/IPv6 yang valid atau CIDR, misalnya 203.0.113.10 atau 203.0.113.0/24.');
                    }
                },
            ],
            'name' => ['required', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['required', 'boolean'],
            'expires_at' => ['nullable', 'date'],
        ]);

        return $data;
    }

    private function isValidIpOrCidr(string $value): bool
    {
        [$ip, $prefix] = array_pad(explode('/', $value, 2), 2, null);
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return false;
        }
        if ($prefix === null) {
            return true;
        }
        if ($prefix === '' || ! ctype_digit($prefix)) {
            return false;
        }

        return (int) $prefix <= (str_contains($ip, ':') ? 128 : 32);
    }
}
