<?php

namespace App\Exports;

use App\Models\User;
use Illuminate\Contracts\Support\Responsable;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class EmployeeExport implements FromCollection, Responsable, ShouldAutoSize, WithHeadings, WithMapping
{
    use Exportable;

    /**
    * It's required to define the fileName within
    * the export class when making use of Responsable.
    */
    protected $fileName;
    protected $index = 0;
    
    // protected $year;
    
    public function __construct()
    {
        $this->fileName = 'daftar-pegawai-' . \Carbon\Carbon::now()->timestamp . '.xlsx';
    }

    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        return User::where('is_admin', 0)->orderBy('name', 'asc')->get();
    }
    
    public function headings(): array
    {
        return [
            'No.',
            'Nama',
            'NIP/Kode Pegawai',
            'Whatsapp',
            'Bagian',
            'Email',
            'Status',
        ];
    }

    public function map($employee): array
    {
        return [
            ++$this->index,
            ucfirst($employee->name ?? '-'),
            strtoupper($employee->employee_code ?? '-'),
            $employee->phone ?? '-',
            ucfirst($employee->department ?? '-'),
            $employee->email ?? '-',
            $employee->is_active ? 'Aktif' : 'Tidak Aktif',
        ];
    }
}
