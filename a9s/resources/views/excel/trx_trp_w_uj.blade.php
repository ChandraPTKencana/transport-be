<table class="line borderless text-center mt-2" style="font-size: x-small;">
  <thead class="text-center" style="background-color: #B0A4A4;">
    <tr>
      <th rowspan="2" style="border: 1px solid black;">No</th>
      <th rowspan="2" style="border: 1px solid black;">ID</th>
      <th rowspan="2" style="border: 1px solid black;">Tanggal</th>
      <th rowspan="2" style="border: 1px solid black;">No Pol</th>
      <th rowspan="2" style="border: 1px solid black;">Jenis</th>
      <th rowspan="2" style="border: 1px solid black;">Tujuan</th>
      <th rowspan="2" style="border: 1px solid black;">Tipe</th>

      <th rowspan="2" style="border: 1px solid black;">Biaya</th>
      <th colspan="2" style="border: 1px solid black;">Peralihan</th>
      <th colspan="2" style="border: 1px solid black;">Cost Center</th>
      <th colspan="3" style="border: 1px solid black;">PVR</th>
      <th colspan="4" style="border: 1px solid black;">PV</th>
      <th colspan="3" style="border: 1px solid black;">Supir</th>
      <th colspan="3" style="border: 1px solid black;">Kernet</th>
      <th colspan="9" style="border: 1px solid black;">Payment</th>
      <th colspan="3" style="border: 1px solid black;">Req Delete</th>
      <th colspan="3" style="border: 1px solid black;">Delete</th>
    </tr>
    <tr>
      <th style="border: 1px solid black;">Type</th>
      <th style="border: 1px solid black;">Target</th>
      <th style="border: 1px solid black;">Code</th>
      <th style="border: 1px solid black;">Desc</th>
      <th style="border: 1px solid black;">No</th>
      <th style="border: 1px solid black;">Total</th>
      <th style="border: 1px solid black;">Complete</th>
      <th style="border: 1px solid black;">Date</th>
      <th style="border: 1px solid black;">No</th>
      <th style="border: 1px solid black;">Total</th>
      <th style="border: 1px solid black;">Complete</th>
      <th style="border: 1px solid black;">Supir</th>
      <th style="border: 1px solid black;">No Rek Supir</th>
      <th style="border: 1px solid black;">Nama Rek Supir</th>
      <th style="border: 1px solid black;">Kernet</th>
      <th style="border: 1px solid black;">No Rek Kernet</th>
      <th style="border: 1px solid black;">Nama Rek Kernet</th>
      <th style="border: 1px solid black;">Method Name</th>
      <th style="border: 1px solid black;">Paid?</th>
      <th style="border: 1px solid black;">ID Duitku Supir</th>
      <th style="border: 1px solid black;">Ket Inquery Duitku Supir</th>
      <th style="border: 1px solid black;">Ket Pengiriman Duitku Supir</th>
      <th style="border: 1px solid black;">ID Duitku Kernet</th>
      <th style="border: 1px solid black;">Ket Inquery Duitku Kernet</th>
      <th style="border: 1px solid black;">Ket Pengiriman Duitku Kernet</th>
      <th style="border: 1px solid black;">Req Delete By</th>
      <th style="border: 1px solid black;">Req Delete At</th>
      <th style="border: 1px solid black;">Req Delete Reason</th>
      <th style="border: 1px solid black;">Delete By</th>
      <th style="border: 1px solid black;">Delete At</th>
      <th style="border: 1px solid black;">Delete Reason</th>
    </tr>
  </thead>
  <tbody>  
    @foreach($data as $k=>$v)
    <tr>
      <td style="border: 1px solid black;">{{$loop->iteration}}</td>
      <td style="border: 1px solid black;">{{ $v["id"] }}</td>
      <td style="border: 1px solid black;">{{ $v["tanggal"] }}</td>
      <td style="border: 1px solid black;">{{ $v["no_pol"] }}</td>
      <td style="border: 1px solid black;">{{ $v["jenis"] }}</td>
      <td style="border: 1px solid black;">{{ $v["xto"] }}</td>
      <td style="border: 1px solid black;">{{ $v["tipe"] }}</td>
      <td style="border: 1px solid black;">{{ $v["amount"] }}</td>

      <td style="border: 1px solid black;">{{ $v["transition_type"] }}</td>
      <td style="border: 1px solid black;">{{ $v["transition_target"] }}</td>
      <td style="border: 1px solid black;">{{ $v["cost_center_code"] }}</td>
      <td style="border: 1px solid black;">{{ $v["cost_center_desc"] }}</td>
      <td style="border: 1px solid black;">{{ $v["pvr_no"] }}</td>
      <td style="border: 1px solid black;">{{ $v["pvr_total"] }}</td>
      <td style="border: 1px solid black;">{{ $v["pvr_had_detail"] ? 'Y' : 'N' }}</td>
      <td style="border: 1px solid black;">{{ $v["pv_datetime"] }}</td>
      <td style="border: 1px solid black;">{{ $v["pv_no"] }}</td>
      <td style="border: 1px solid black;">{{ $v["pv_total"] }}</td>
      <td style="border: 1px solid black;">{{ $v["pv_complete"] ? 'Y' : 'N' }}</td>
      <td style="border: 1px solid black;">{{ $v["supir"] }}</td>
      <td style="border: 1px solid black;">{{ $v["supir_rek_no"] }}</td>
      <td style="border: 1px solid black;">{{ $v["supir_rek_name"] }}</td>
      <td style="border: 1px solid black;">{{ $v["kernet"] }}</td>
      <td style="border: 1px solid black;">{{ $v["kernet_rek_no"] }}</td>
      <td style="border: 1px solid black;">{{ $v["kernet_rek_name"] }}</td>
      <td style="border: 1px solid black;">{{ $v["payment_method"]['name'] }}</td>
      <td style="border: 1px solid black;">{{ $v["received_payment"] ? 'Y' : 'N' }}</td>
      <td style="border: 1px solid black;">{{ $v["duitku_supir_disburseId"] }}</td>
      <td style="border: 1px solid black;">{{ $v["duitku_supir_inv_res_desc"] }}</td>
      <td style="border: 1px solid black;">{{ $v["duitku_supir_trf_res_desc"] }}</td>
      <td style="border: 1px solid black;">{{ $v["duitku_kernet_disburseId"] }}</td>
      <td style="border: 1px solid black;">{{ $v["duitku_kernet_inv_res_desc"] }}</td>
      <td style="border: 1px solid black;">{{ $v["duitku_kernet_trf_res_desc"] }}</td>
      <td style="border: 1px solid black;">{{ $v["req_deleted"] ? $v["req_deleted_by"]["username"] : '' }}</td>
      <td style="border: 1px solid black;">{{ $v["req_deleted_at"] }}</td>
      <td style="border: 1px solid black;">{{ $v["req_deleted_reason"] }}</td>
      <td style="border: 1px solid black;">{{ $v["deleted"] ? $v["deleted_by"]["username"] : '' }}</td>
      <td style="border: 1px solid black;">{{ $v["deleted_at"] }}</td>
      <td style="border: 1px solid black;">{{ $v["deleted_reason"] }}</td>
    </tr>
    <tr></tr>
    <tr>
      <td></td>
      <td></td>
      <td></td>
      <td style="border: 1px solid black;">No</td>
      <td style="border: 1px solid black;">Desc</td>
      <td style="border: 1px solid black;">Qty</td>
      <td style="border: 1px solid black;">Harga</td>
      <td style="border: 1px solid black;">Jumlah</td>
    </tr>
    @foreach($v["details_uj"] as $kv=>$vv)
    <tr>
      <td></td>
      <td></td>
      <td></td>
      <td style="border: 1px solid black;">{{ $vv["ordinal"] }}</td>
      <td style="border: 1px solid black;">{{ $vv["xdesc"] }}</td>
      <td style="border: 1px solid black;">{{ $vv["qty"] }}</td>
      <td style="border: 1px solid black;">{{ $vv["harga"] }}</td>
      <td style="border: 1px solid black;">{{ $vv["harga"] * $vv["qty"] }}</td>
    </tr>
    @endforeach
    <tr></tr>

    
    @endforeach
  </tbody>
</table>
