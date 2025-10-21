<table class="line borderless text-center mt-2" style="font-size: x-small;">
  <thead class="text-center" style="background-color: #B0A4A4;">
    
    <tr >
      <th colspan="13" style="text-align: center;">
        PT.{{ $data['pabrik'] }}
      </th>
    </tr>
    <tr >
      <th colspan="13" style="text-align: center;">
        Laporan Pengiriman Uang Jalan
      </th>
    </tr>
    <tr >
      <th colspan="13" style="text-align: center;">
        Tanggal Permintaan: {{ date('d-m-Y H:i:s',strtotime($data['created_at'])) }}
      </th>
    </tr>
    <tr>
      <th style="border: 1px solid black;">No</th>
      <th style="border: 1px solid black;">Tujuan</th>
      <th style="border: 1px solid black;">Produk</th>
      <th style="border: 1px solid black;">No Trx</th>
      <th style="border: 1px solid black;">No Pol</th>
      <th style="border: 1px solid black;">Jabatan</th>
      <th style="border: 1px solid black;">Nama</th>
      <th style="border: 1px solid black;">Jumlah</th>
      <th style="border: 1px solid black;">Potongan</th>
      <th style="border: 1px solid black;">ExtraMoney</th>
      <th style="border: 1px solid black;"></th>
      <th style="border: 1px solid black;">No Rek</th>
      <th style="border: 1px solid black;">Nama Di Bank</th>
      <th style="border: 1px solid black;">Nominal Transfer</th>
    </tr>
  </thead>
  <tbody>
    @php
      $row_jump = 4;
    @endphp
    @foreach($data['details'] as $k=>$v)
    <tr>
      <td style="border: 1px solid black;">{{ $v["jabatan"]!="KERNET" ? $v["no"] :"" }}</td>
      <td style="border: 1px solid black; " class="p-1">{{ $v["jabatan"]!="KERNET" ? $v["tujuan"] : "" }}</td>
      <td style="border: 1px solid black; " class="p-1">{{ $v["jabatan"]!="KERNET" ? $v["produk"] : "" }}</td>
      <td style="border: 1px solid black; " class="p-1">{{ $v["jabatan"]!="KERNET" ? $v["id"] : "" }}</td>
      <td style="border: 1px solid black; " class="p-1">{{ $v["jabatan"]!="KERNET" ? $v["no_pol"] : "" }}</td>
      <td style="border: 1px solid black; " class="p-1">{{ $v["jabatan"] }}</td>
      <td style="border: 1px solid black; " class="p-1">{{ $v["nama"] }}</td>
      <td style="border: 1px solid black; " class="p-1">{{ $v["nominal"] }}</td>
      <td style="border: 1px solid black; " class="p-1"> {{ $v["potongan_trx_ttl"] }} </td>
      <td style="border: 1px solid black; " class="p-1"> {{ $v["extra_money_trx_ttl"] }} </td>
      <td style="border: 1px solid black; " class="p-1"> </td>
      <td style="border: 1px solid black; " class="p-1">{{ $v["rek_no"] }}</td>
      <td style="border: 1px solid black; " class="p-1">{{ $v["rek_name"] }}</td>
      <td style="border: 1px solid black; " class="p-1">{{ $v["jumlah"] }}</td>
    </tr>
    @endforeach
    <tr>
    <td colspan="7" style="text-align: right; border: 1px solid black; font-weight:bold;"> Grand Total</td>
    <td style="border: 1px solid black; font-weight:bold;" class="p-1" > =SUM(H{{ $row_jump + 1 }}:H{{ count($data['details']) + $row_jump }})</td>
    <td style="border: 1px solid black; font-weight:bold;" class="p-1" > =SUM(I{{ $row_jump + 1 }}:I{{ count($data['details']) + $row_jump }})</td>
    <td style="border: 1px solid black; font-weight:bold;" class="p-1" > =SUM(J{{ $row_jump + 1 }}:J{{ count($data['details']) + $row_jump }})</td>
    <td colspan="3" style="border: 1px solid black;"></td>
    <td style="border: 1px solid black; font-weight:bold;" class="p-1" > =SUM(N{{ $row_jump + 1 }}:N{{ count($data['details']) + $row_jump }})</td>
    </tr>
  </tbody>
</table>