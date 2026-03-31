<?php

namespace App\Http\Controllers;

use App\Http\Resources\AlumniResource;
use App\Models\Alumni;
use Illuminate\Http\Request;

class AlumniSearchController extends Controller
{
    public function search(Request $request)
    {
        $validated = $request->validate([
            'nim' => ['nullable', 'string', 'alpha_num', 'max:32'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'nama' => ['nullable', 'string', 'max:100'],
            'tanggal_lahir' => ['nullable', 'date'],
            'dob' => ['nullable', 'date'],
        ]);

        if (empty($validated['nim']) && empty($validated['email']) && empty($validated['nama']) && empty($validated['tanggal_lahir']) && empty($validated['dob'])) {
            return response()->json(['message' => 'Parameter pencarian tidak boleh kosong.'], 422);
        }

        $query = Alumni::query();

        if ($nim = $request->query('nim')) {
            $query->where('nim', 'like', '%' . $nim . '%');
        }

        if ($email = $request->query('email')) {
            $query->where('email', 'like', '%' . $email . '%');
        }

        if ($nama = $request->query('nama')) {
            $query->where('nama', 'like', '%' . $nama . '%');
        }

        $dob = $request->query('tanggal_lahir') ?? $request->query('dob');
        if ($dob) {
            $query->whereDate('tanggal_lahir', $dob);
        }

        $results = $query->limit(20)->get();

        return AlumniResource::collection($results);
    }
}
