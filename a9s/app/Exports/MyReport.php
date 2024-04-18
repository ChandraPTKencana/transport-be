<?php
namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\Exportable;


class MyReport implements FromView
{
    use Exportable;
    public $data;
    public $report_view;

    public function __construct($data,$report_view)
    {
        $this->data = $data;
        $this->report_view = $report_view;
    }

    public function view(): View
    {
        return view( $this->report_view, $this->data);
    }


}
?>
