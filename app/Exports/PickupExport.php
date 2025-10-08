<?php

namespace App\Exports;

use App\Models\Pickup;
use Carbon\Carbon;
use Illuminate\Contracts\Support\Responsable;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class PickupExport implements FromCollection, Responsable, ShouldAutoSize, WithHeadings, WithMapping
{
    use Exportable;

    /**
    * It's required to define the fileName within
    * the export class when making use of Responsable.
    */
    protected $fileName;
    protected $index = 0;
    protected $start;
    protected $end;
    
    public function __construct($start = null, $end = null)
    {
        $this->fileName = 'daftar-pengambilan-makanan-' . \Carbon\Carbon::now()->timestamp . '.xlsx';

        if ($start && $end) {
            $this->start = Carbon::parse($start)->startOfDay();
            $this->end = Carbon::parse($end)->addDay()->startOfDay();
        } elseif ($start) {
            $this->start = Carbon::parse($start)->startOfDay();
            $this->end = Carbon::parse($start)->addDay()->startOfDay();
        }
    }

    public function today()
    {
        $this->start = now()->startOfDay();
        $this->end = now()->addDay()->startOfDay();

        return $this;
    }

    public function yesterday()
    {
        $this->start = now()->subDay()->startOfDay();
        $this->end = now()->startOfDay();

        return $this;
    }

    public function last7Days()
    {
        $this->start = now()->subDays(7)->startOfDay();
        $this->end = now()->addDay()->startOfDay();

        return $this;
    }

    public function last30Days()
    {
        $this->start = now()->subDays(30)->startOfDay();
        $this->end = now()->addDay()->startOfDay();

        return $this;
    }

    public function thisMonth()
    {
        $this->start = now()->startOfMonth()->startOfDay();
        $this->end = now()->endOfMonth()->addDay()->startOfDay();

        return $this;
    }

    public function lastMonth()
    {
        $this->start = now()->subMonth()->startOfMonth()->startOfDay();
        $this->end = now()->subMonth()->endOfMonth()->addDay()->startOfDay();

        return $this;
    }

    public function thisYear()
    {
        $this->start = now()->startOfYear()->startOfDay();
        $this->end = now()->endOfYear()->addDay()->startOfDay();

        return $this;
    }

    public function lastYear()
    {
        $this->start = now()->subYear()->startOfYear()->startOfDay();
        $this->end = now()->subYear()->endOfYear()->addDay()->startOfDay();

        return $this;
    }

    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        // return Pickup::all();
        $q = Pickup::query();

        $q->join('users as officer', 'officer.id', '=', 'pickups.officer_id')
        ->join('users as employee', 'employee.id', '=', 'pickups.picked_by')
        ->join('meal_sessions', 'meal_sessions.id', '=', 'pickups.meal_session_id')
        ->join('meal_windows', 'meal_windows.id', '=', 'meal_sessions.meal_window_id')
        ->select('pickups.*', 'employee.name as employee_name', 'employee.department as department', 'meal_sessions.date as production_date', 'meal_windows.name as window_name', 'officer.name as officer_name');

        // department filter
        if ($this->start && $this->end) {
            $q->where('pickups.picked_at', '>=', $this->start)
            ->where('pickups.picked_at', '<', $this->end);
        }

        $q->orderBy('pickups.picked_at', 'asc');

        return $q->get();
    }

    public function headings(): array
    {
        return [
            // 'No.',
            'Tanggal',
            'Jam',
            'Waktu Makan',
            'Nama',
            'Bagian',
            'Metode',
            'Tanggal Produksi',
            'Petugas',
            'Dialihkan/Diubah',
        ];
    }

    public function map($pickup): array
    {
        if($pickup->method == 'qr') {
            $pickup->method = 'QR';
        }
        if($pickup->method != '') {
            $pickup->method = ucfirst($pickup->method);
        }

        return [
            // ++$this->index,
            $pickup->date_indo,
            $pickup->time_indo,
            ucfirst($pickup->window_name ?? '-'),
            ucfirst($pickup->employee_name ?? '-'),
            ucfirst($pickup->department ?? '-'),
            $pickup->method ?? '-',
            Carbon::parse($pickup->production_date)->format('d-m-Y'),
            ucfirst($pickup->officer_name ?? '-'),
            ucfirst($pickup->overriden_reason ?? '-'),
        ];
    }
}
