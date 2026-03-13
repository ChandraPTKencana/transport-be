<table class="line borderless text-center mt-2" style="font-size: x-small;">
  <thead class="text-center" style="background-color: #B0A4A4;">

    <tr>
      <th rowspan="3" style="border: 1px solid black;">No</th>
      <th rowspan="3" style="border: 1px solid black;">ID</th>
      <th rowspan="3" style="border: 1px solid black;">U.Jalan Per</th>
      <th rowspan="3" style="border: 1px solid black;">No Pol</th>

      <th rowspan="3" style="border: 1px solid black;">Tujuan</th>
      <th rowspan="3" style="border: 1px solid black;">Info</th>
      <th rowspan="3" style="border: 1px solid black;">Tipe</th>
      <th rowspan="3" style="border: 1px solid black;">Jenis</th>
      <th rowspan="3" style="border: 1px solid black;">Amount</th>
      <th rowspan="2" colspan="2" style="border: 1px solid black;">Cost Center</th>
      <th rowspan="2" colspan="3" style="border: 1px solid black;">PVR</th>
      <th rowspan="2" colspan="3" style="border: 1px solid black;">PV</th>
      <th rowspan="2" colspan="2" style="border: 1px solid black;">Peralihan</th>
      <th rowspan="2" colspan="8" style="border: 1px solid black;">Ticket A</th>
      <th rowspan="2" colspan="8" style="border: 1px solid black;">Ticket B</th>

      <th colspan="12" style="border: 1px solid black;">Susut</th>

      <th rowspan="3" style="border: 1px solid black;">Supir</th>
      <th rowspan="3" style="border: 1px solid black;">Kernet</th>
      <th rowspan="3" style="border: 1px solid black;">Created At</th>
      <th rowspan="3" style="border: 1px solid black;">Updated At</th>
    </tr>
    <tr>
      <th colspan="4" style="border: 1px solid black;"> Bruto </th>
      <th colspan="4" style="border: 1px solid black;"> Tara </th>
      <th colspan="4" style="border: 1px solid black;"> Netto </th>     
    </tr>
    <tr>
      <th style="border: 1px solid black;">Code</th>
      <th style="border: 1px solid black;">Desc</th>
      <th style="border: 1px solid black;">No</th>
      <th style="border: 1px solid black;">Total</th>
      <th style="border: 1px solid black;">Completed</th>
      <th style="border: 1px solid black;">Date</th>
      <th style="border: 1px solid black;">No</th>
      <th style="border: 1px solid black;">Total</th>
      <th style="border: 1px solid black;">Tipe</th>
      <th style="border: 1px solid black;">Target</th>

      <th style="border: 1px solid black;">No</th>
      <th style="border: 1px solid black;">Bruto</th>
      <th style="border: 1px solid black;">Tara</th>
      <th style="border: 1px solid black;">Netto</th>
      <th style="border: 1px solid black;">Supir</th>
      <th style="border: 1px solid black;">No Pol</th>
      <th style="border: 1px solid black;">In At</th>
      <th style="border: 1px solid black;">Out At</th>

      <th style="border: 1px solid black;">No</th>
      <th style="border: 1px solid black;">Bruto</th>
      <th style="border: 1px solid black;">Tara</th>
      <th style="border: 1px solid black;">Netto</th>
      <th style="border: 1px solid black;">Supir</th>
      <th style="border: 1px solid black;">No Pol</th>
      <th style="border: 1px solid black;">In At</th>
      <th style="border: 1px solid black;">Out At</th>

      <th style="border: 1px solid black;">Berangkat</th>
      <th style="border: 1px solid black;">Kembali</th>
      <th style="border: 1px solid black;">Selisih</th>
      <th style="border: 1px solid black;">%</th>
      <th style="border: 1px solid black;">Berangkat</th>
      <th style="border: 1px solid black;">Kembali</th>
      <th style="border: 1px solid black;">Selisih</th>
      <th style="border: 1px solid black;">%</th>
      <th style="border: 1px solid black;">Berangkat</th>
      <th style="border: 1px solid black;">Kembali</th>
      <th style="border: 1px solid black;">Selisih</th>
      <th style="border: 1px solid black;">%</th>
    </tr>
  </thead>
  <tbody>  
    @foreach($data as $k=>$v)
    <tr>
      <td style="border: 1px solid black;">{{$loop->iteration}}</td>
      <td style="border: 1px solid black;">{{ $v["id"] }}</td>
      <td style="border: 1px solid black;">{{ $v["tanggal"] }}</td>
      <td style="border: 1px solid black;">{{ $v["no_pol"] }}</td>
      <td style="border: 1px solid black;">{{ $v["xto"] }}</td>
      <td style="border: 1px solid black;">{{ $v["uj"]["asst_opt"] }}</td>
      <td style="border: 1px solid black;">{{ $v["tipe"] }}</td>
      <td style="border: 1px solid black;">{{ $v["jenis"] }}</td>
      <td style="border: 1px solid black;">{{ $v["amount"] }}</td>
      <td style="border: 1px solid black;">{{ $v["cost_center_code"] }}</td>
      <td style="border: 1px solid black;">{{ $v["cost_center_desc"] }}</td>
      <td style="border: 1px solid black;">{{ $v["pvr_no"] }}</td>
      <td style="border: 1px solid black;">{{ $v["pvr_total"] }}</td>
      <td style="border: 1px solid black;">{{ $v["pvr_had_detail"] == 1 ? 'Y' : 'N' }}</td>
      <td style="border: 1px solid black;">{{ $v["pv_datetime"] }}</td>
      <td style="border: 1px solid black;">{{ $v["pv_no"] }}</td>
      <td style="border: 1px solid black;">{{ $v["pv_total"] }}</td>
      <td style="border: 1px solid black;">{{ $v["transition_type"] }}</td>
      <td style="border: 1px solid black;">{{ $v["transition_target"] }}</td>

      <td style="border: 1px solid black;">{{ $v["ticket_a_no"] }}</td>
      <td style="border: 1px solid black;">{{ $v["ticket_a_bruto"] }}</td>
      <td style="border: 1px solid black;">{{ $v["ticket_a_tara"] }}</td>
      <td style="border: 1px solid black;">{{ $v["ticket_a_netto"] }}</td>
      <td style="border: 1px solid black;">{{ $v["ticket_a_supir"] }}</td>
      <td style="border: 1px solid black;">{{ $v["ticket_a_no_pol"] }}</td>
      <td style="border: 1px solid black;">{{ $v["ticket_a_in_at"] }}</td>
      <td style="border: 1px solid black;">{{ $v["ticket_a_out_at"] }}</td>

      <td style="border: 1px solid black;">{{ $v["ticket_b_no"] }}</td>
      <td style="border: 1px solid black;">{{ $v["ticket_b_bruto"] }}</td>
      <td style="border: 1px solid black;">{{ $v["ticket_b_tara"] }}</td>
      <td style="border: 1px solid black;">{{ $v["ticket_b_netto"] }}</td>
      <td style="border: 1px solid black;">{{ $v["ticket_b_supir"] }}</td>
      <td style="border: 1px solid black;">{{ $v["ticket_b_no_pol"] }}</td>
      <td style="border: 1px solid black;">{{ $v["ticket_b_in_at"] }}</td>
      <td style="border: 1px solid black;">{{ $v["ticket_b_out_at"] }}</td>
      
      <td style="border: 1px solid black;">{{ $v["ticket_a_bruto"] }}</td>
      <td style="border: 1px solid black;">{{ $v["ticket_b_bruto"] }}</td>
      <td style="border: 1px solid black;">{{ $v["ticket_b_a_bruto"] }}</td>
      <td style="border: 1px solid black;">{{ $v["ticket_b_a_bruto_persen"] }}</td>

      <td style="border: 1px solid black;">{{ $v["ticket_a_tara"] }}</td>
      <td style="border: 1px solid black;">{{ $v["ticket_b_tara"] }}</td>
      <td style="border: 1px solid black;">{{ $v["ticket_b_a_tara"] }}</td>
      <td style="border: 1px solid black;">{{ $v["ticket_b_a_tara_persen"] }}</td>

      <td style="border: 1px solid black;">{{ $v["ticket_a_netto"] }}</td>
      <td style="border: 1px solid black;">{{ $v["ticket_b_netto"] }}</td>
      <td style="border: 1px solid black;">{{ $v["ticket_b_a_netto"] }}</td>
      <td style="border: 1px solid black;">{{ $v["ticket_b_a_netto_persen"] }}</td>

      <td style="border: 1px solid black;">{{ $v["supir"] }}</td>
      <td style="border: 1px solid black;">{{ $v["kernet"] }}</td>
      <td style="border: 1px solid black;">{{ date('d-m-Y H:i:s',strtotime($v["created_at"])); }}</td>
      <td style="border: 1px solid black;">{{ date('d-m-Y H:i:s',strtotime($v["updated_at"])); }}</td>
    </tr>
    @endforeach
  </tbody>
</table>
