<table class="line borderless text-center mt-2" style="font-size: x-small;">
  <thead class="text-center" style="background-color: #B0A4A4;">

    <tr>
      <th rowspan="2" style="border: 1px solid black;">No</th>
      <th rowspan="2" style="border: 1px solid black;">ID</th>
      <th rowspan="2" style="border: 1px solid black;">No Pol</th>
      <th rowspan="2" style="border: 1px solid black;">Tujuan</th>
      <th rowspan="2" style="border: 1px solid black;">Details Dates</th>
      <th rowspan="2" style="border: 1px solid black;">Salary Paid</th>
      <th colspan="3" style="border: 1px solid black;">Supir</th>
      <th colspan="3" style="border: 1px solid black;">Kernet</th>
      <th colspan="3" style="border: 1px solid black;">Standby Mst</th>
      <th colspan="2" style="border: 1px solid black;">Standby Value</th>

      <th rowspan="2" style="border: 1px solid black;">Note For Remarks</th>
      <th rowspan="2" style="border: 1px solid black;">Trx Trp</th>
      <th colspan="2" style="border: 1px solid black;">Peralihan</th>
      <th colspan="2" style="border: 1px solid black;">Cost Center</th>
      <th colspan="2" style="border: 1px solid black;">PVR</th>
      <th colspan="3" style="border: 1px solid black;">PV</th>
      <th rowspan="2" style="border: 1px solid black;">Created At</th>
      <th rowspan="2" style="border: 1px solid black;">Updated At</th>
    </tr>
    <tr>      
      <th style="border: 1px solid black;">Nama</th>
      <th style="border: 1px solid black;">No Rek</th>
      <th style="border: 1px solid black;">Nama Rek</th>

      <th style="border: 1px solid black;">Nama</th>
      <th style="border: 1px solid black;">No Rek</th>
      <th style="border: 1px solid black;">Nama Rek</th>

      <th style="border: 1px solid black;">Nama</th>
      <th style="border: 1px solid black;">Tipe</th>
      <th style="border: 1px solid black;">Biaya</th>

      <th style="border: 1px solid black;">Jumlah</th>
      <th style="border: 1px solid black;">Total</th>

      <th style="border: 1px solid black;">Type</th>
      <th style="border: 1px solid black;">Target</th>

      <th style="border: 1px solid black;">Code</th>
      <th style="border: 1px solid black;">Desc</th>

      <th style="border: 1px solid black;">No</th>
      <th style="border: 1px solid black;">Total</th>

      <th style="border: 1px solid black;">Date</th>
      <th style="border: 1px solid black;">No</th>
      <th style="border: 1px solid black;">Total</th>

    </tr>
  </thead>
  <tbody>  
    @foreach($data as $k=>$v)
    <tr>
      <td style="border: 1px solid black;">{{ $loop->iteration }}</td>
      <td style="border: 1px solid black;">{{ $v["id"] }}</td>
      <td style="border: 1px solid black;">{{ $v["no_pol"] }}</td>
      <td style="border: 1px solid black;">{{ $v["xto"] }}</td>
      
      <td style="border: 1px solid black;">
        @foreach($v['details'] as $kd=>$vd)
          {{ $vd["tanggal"] }}
        @endforeach
      </td>
      <td style="border: 1px solid black;">{{ $v["salary_paid_periode"] }}</td>

      <td style="border: 1px solid black;">{{ $v["supir"] }}</td>
      <td style="border: 1px solid black;">{{ $v["supir_rek_no"] ? mb_strtoupper("'".$v["supir_rek_no"],'UTF-8') : '' }}</td>
      <td style="border: 1px solid black;">{{ $v["supir_rek_name"] }}</td>

      <td style="border: 1px solid black;">{{ $v["kernet"] }}</td>
      <td style="border: 1px solid black;">{{ $v["kernet_rek_no"] ? mb_strtoupper("'".$v["kernet_rek_no"],'UTF-8') : '' }}</td>
      <td style="border: 1px solid black;">{{ $v["kernet_rek_name"] }}</td>


      <td style="border: 1px solid black;">{{ $v["standby_mst_"]["name"] }}</td>
      <td style="border: 1px solid black;">{{ $v["standby_mst_"]["tipe"] }}</td>
      <td style="border: 1px solid black;">{{ $v["standby_mst_"]["amount"] }}</td>

      <td style="border: 1px solid black;">{{ $v["details_count"] }}</td>
      <td style="border: 1px solid black;">{{ $v["standby_mst_"]["amount"] * $v["details_count"] }}</td>

      <td style="border: 1px solid black;">{{ $v["note_for_remarks"] }}</td>
      <td style="border: 1px solid black;">{{ $v["trx_trp_id"] }}</td>

      <td style="border: 1px solid black;">{{ $v["transition_type"] }}</td>
      <td style="border: 1px solid black;">{{ $v["transition_target"] }}</td>

      <td style="border: 1px solid black;">{{ $v["cost_center_code"] }}</td>
      <td style="border: 1px solid black;">{{ $v["cost_center_desc"] }}</td>
      
      <td style="border: 1px solid black;">{{ $v["pvr_no"] }}</td>
      <td style="border: 1px solid black;">{{ $v["pvr_total"] }}</td>

      <td style="border: 1px solid black;">{{ $v["pv_datetime"] }}</td>
      <td style="border: 1px solid black;">{{ $v["pv_no"] }}</td>
      <td style="border: 1px solid black;">{{ $v["pv_total"] }}</td>

      <td style="border: 1px solid black;">{{ $v["created_at"] }}</td>
      <td style="border: 1px solid black;">{{ $v["updated_at"] }}</td>
    </tr>
    @endforeach
  </tbody>
</table>
