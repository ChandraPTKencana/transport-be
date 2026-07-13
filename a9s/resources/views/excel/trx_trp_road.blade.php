<table class="line borderless text-center mt-2" style="font-size: x-small;">
  <thead class="text-center" style="background-color: #B0A4A4;">
    <tr>
      <th style="border: 1px solid black;">Tanggal</th>
      <th style="border: 1px solid black;">Nopol</th>
      <th style="border: 1px solid black;">Jenis</th>
      <th style="border: 1px solid black;">Tujuan</th>
      <th style="border: 1px solid black;">WaktuBerangkat</th>
      <th style="border: 1px solid black;">WaktuTiba</th>
    </tr>
  </thead>
  <tbody>  
    @foreach($data as $k=>$v)
    <tr>
      <td style="border: 1px solid black;">{{ $v["tanggal"] }}</td>
      <td style="border: 1px solid black;">{{ $v["no_pol"] }}</td>
      <td style="border: 1px solid black;">{{ $v["jenis"] }}</td>
      <td style="border: 1px solid black;">{{ $v["xto"] }}</td>
      <td style="border: 1px solid black;">{{ ($v["jenis"]=='CPO' || $v["jenis"]=='PK' || $v["jenis"]=='CANGKANG') ? ($v['ritase_leave_at']??'') : ($v['ritase_return_at']??'')  }}</td>
      <td style="border: 1px solid black;">{{ ($v["jenis"]=='CPO' || $v["jenis"]=='PK' || $v["jenis"]=='CANGKANG') ? ($v['ritase_arrive_at']??'') : ($v['ritase_till_at']??'') }}</td>
    </tr>
    @endforeach
  </tbody>
</table>
