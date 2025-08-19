<table class="line borderless text-center mt-2" style="font-size: x-small;">
  <thead class="text-center" style="background-color: #B0A4A4;">

    <tr>
      <th rowspan="3" style="border: 1px solid black;">No</th>
      <th rowspan="3" style="border: 1px solid black;">ID</th>
      <th rowspan="3" style="border: 1px solid black;">Tanggal</th>
      <th rowspan="3" style="border: 1px solid black;">No Pol</th>
      <th rowspan="3" style="border: 1px solid black;">Jenis</th>
      <th rowspan="3" style="border: 1px solid black;">Tujuan</th>
      <th rowspan="3" style="border: 1px solid black;">Tipe</th>
      <th rowspan="3" style="border: 1px solid black;">Supir</th>
      <th rowspan="3" style="border: 1px solid black;">Kernet</th>
      <th rowspan="3" style="border: 1px solid black;">Biaya</th>
      <th rowspan="2" colspan="2" style="border: 1px solid black;">Peralihan</th>

      <th rowspan="2" colspan="8"  style="border: 1px solid black;">Ticket A</th>
      <th rowspan="2" colspan="8"  style="border: 1px solid black;">Ticket B</th>
      <th rowspan="1" colspan="6"  style="border: 1px solid black;">Susut</th>
      <th rowspan="3" style="border: 1px solid black;">PV_No</th>
      <th rowspan="2" colspan="3" style="border: 1px solid black;">Req Deleted</th>
      <th rowspan="2" colspan="3" style="border: 1px solid black;">Deleted</th>
    </tr>
    <tr>
      <th rowspan="1" colspan="2">Bruto</th>
      <th rowspan="1" colspan="2">Tara</th>
      <th rowspan="1" colspan="2">Netto</th>
    </tr>
    <tr>
      <th style="border: 1px solid black;">Type</th>
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

      <th style="border: 1px solid black;">Selisih</th>
      <th style="border: 1px solid black;">%</th>
      <th style="border: 1px solid black;">Selisih</th>
      <th style="border: 1px solid black;">%</th>
      <th style="border: 1px solid black;">Selisih</th>
      <th style="border: 1px solid black;">%</th>

      <th style="border: 1px solid black;"> By </th>
      <th style="border: 1px solid black;"> At </th>
      <th style="border: 1px solid black;"> Reason </th>

      <th style="border: 1px solid black;"> By </th>
      <th style="border: 1px solid black;"> At </th>
      <th style="border: 1px solid black;"> Reason </th>
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
      <td style="border: 1px solid black;">{{ $v["supir"] }}</td>
      <td style="border: 1px solid black;">{{ $v["kernet"] }}</td>
      <td style="border: 1px solid black;">{{ $v["amount"] }}</td>
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

      <td style="border: 1px solid black;">{{ $v["ticket_b_a_bruto"] }}</td>
      <td style="border: 1px solid black;">{{ $v["ticket_b_a_bruto_persen"] }}</td>

      <td style="border: 1px solid black;">{{ $v["ticket_b_a_tara"] }}</td>
      <td style="border: 1px solid black;">{{ $v["ticket_b_a_tara_persen"] }}</td>

      <td style="border: 1px solid black;">{{ $v["ticket_b_a_netto"] }}</td>
      <td style="border: 1px solid black;">{{ $v["ticket_b_a_netto_persen"] }}</td>
      
      <td style="border: 1px solid black;">{{ $v["ticket_b_a_netto_persen"] }}</td>

      <td style="border: 1px solid black;">{{ $v["req_deleted"] ? $v["req_deleted_by"]["username"] : '' }}</td>
      <td style="border: 1px solid black;">{{ $v["req_deleted_at"] }}</td>
      <td style="border: 1px solid black;">{{ $v["req_deleted_reason"] }}</td>
      <td style="border: 1px solid black;">{{ $v["deleted"] ? $v["deleted_by"]["username"] : '' }}</td>
      <td style="border: 1px solid black;">{{ $v["deleted_at"] }}</td>
      <td style="border: 1px solid black;">{{ $v["deleted_reason"] }}</td>

    </tr>
    @endforeach
  </tbody>
</table>
