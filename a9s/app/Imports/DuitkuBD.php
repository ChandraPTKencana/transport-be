<?php

namespace App\Imports;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class DuitkuBD implements WithMultipleSheets 
{

    public function sheets(): array
    {
        return [
            'Template' => new SheetImportClass(),
            // or by index:
            // 0 => new SheetImportClass(),
        ];
    }
}

class SheetImportClass implements ToModel
{
    public function model(array $row)
    {
        // Process each row
        return [
           'Amount'     => $row[0],
           'BankCode'    => $row[1], 
           'BankName' => $row[2],
           'BankAccountName' => $row[3],
           'BankAccountNumber' => $row[4],
           'Description' => $row[5],
           'Email' => $row[6],
           'Method' => $row[7],
        ];
    }
    
    // Optional: Limit the data read
    public function startRow(): int
    {
        return 0; // Skip header row
    }
}