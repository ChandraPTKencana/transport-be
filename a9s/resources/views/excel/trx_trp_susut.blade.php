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
      <th rowspan="2" style="border: 1px solid black;">Supir</th>
      <th rowspan="2" style="border: 1px solid black;">Kernet</th>

      <th rowspan="2" style="border: 1px solid black;">Berangkat</th>
      <th rowspan="2" style="border: 1px solid black;">Kembali</th>
      <th colspan="4" style="border: 1px solid black;">Bruto</th>
      <th colspan="4" style="border: 1px solid black;">Tara</th>
      <th colspan="4" style="border: 1px solid black;">Netto</th>
      <th colspan="1" style="border: 1px solid black;">Biaya</th>
    </tr>
    <tr>
      <th>Kirim</th>
      <th>Terima</th>
      <th>Selisih</th>
      <th>% Selisih</th>
      <th>Kirim</th>
      <th>Terima</th>
      <th>Selisih</th>
      <th>% Selisih</th>
      <th>Kirim</th>
      <th>Terima</th>
      <th>Selisih</th>
      <th>% Selisih</th>
      <th>Ujalan</th>
    </tr>
  </thead>
  <tbody>  
    @foreach($data as $k=>$v)
    <tr>
      <td>{{$loop->iteration}}</td>
      <td>{{ $v["id"] }}</td>
      <td>{{ $v["tanggal"] }}</td>
      <td>{{ $v["no_pol"] }}</td>
      <td>{{ $v["jenis"] }}</td>
      <td>{{ $v["xto"] }}</td>
      <td>{{ $v["tipe"] }}</td>
      <td>{{ $v["supir"] }}</td>
      <td>{{ $v["kernet"] }}</td>

      <td>{{ $v["ticket_a_out_at"] }}</td>
      <td>{{ $v["ticket_b_in_at"] }}</td>
      <td>{{ $v["ticket_a_bruto"] }}</td>
      <td>{{ $v["ticket_b_bruto"] }}</td>
      <td>{{ $v["ticket_b_a_bruto"] }}</td>
      <td>{{ $v["ticket_b_a_bruto_persen"] }}</td>
      <td>{{ $v["ticket_a_tara"] }}</td>
      <td>{{ $v["ticket_b_tara"] }}</td>
      <td>{{ $v["ticket_b_a_tara"] }}</td>
      <td>{{ $v["ticket_b_a_tara_persen"] }}</td>
      <td>{{ $v["ticket_a_netto"] }}</td>
      <td>{{ $v["ticket_b_netto"] }}</td>
      <td>{{ $v["ticket_b_a_netto"] }}</td>
      <td>{{ $v["ticket_b_a_netto_persen"] }}</td>
      <td>{{ $v["amount"] }}</td>
    </tr>
    @endforeach
  </tbody>
</table>
