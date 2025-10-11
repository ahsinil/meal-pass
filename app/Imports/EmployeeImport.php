<?php

namespace App\Imports;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\ToModel;
use Illuminate\Support\Str;

class EmployeeImport implements ToModel
{
    use Importable;

    /**
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */
    public function model(array $row)
    {
        // Password
        $password = ($row[4]) ? $row[4] . substr($row[1], -4) : Str::random(8);

        return new User([
            'name' => $row[0],
            // 'first_name' => $row[0],
            'last_name' => $password,
            'phone' => $row[1],
            'email' => $row[2],
            'password' => Hash::make($password),
            'department' => $row[3],
            'employee_code' => $row[4],
            'pickup_code' => Str::upper(Str::random(6)),
            'is_admin' => 0,
            'is_active' => 1
        ]);
    }
}
