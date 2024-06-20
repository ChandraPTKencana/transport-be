<table class="line borderless text-center mt-2" style="font-size: x-small;">
  <thead class="text-center" style="background-color: #B0A4A4;">
    <tr>
      <th style="border: 1px solid black;">Nama</th>
      <th style="border: 1px solid black;">Jabatan</th>
      <th style="border: 1px solid black;">Tanggal</th>
      <th style="border: 1px solid black;">ID</th>
      <th style="border: 1px solid black;">No Pol</th>
      <th style="border: 1px solid black;">Lokasi</th>
      <th style="border: 1px solid black;">UJ.Gaji</th>
      <th style="border: 1px solid black;">UJ.Makan</th>
      <th style="border: 1px solid black;">SB.Gaji</th>
      <th style="border: 1px solid black;">SB.Makan</th>
      <th style="border: 1px solid black;">Total</th>
    </tr>
  </thead>
  <tbody>
    @foreach($data as $k=>$v)
    <tr>
      <td>{{ $v["nama"] }}</td>
      <td class="text-right p-1">{{ $v["jabatan"] }}</td>
      <td class="text-right p-1">{{ $v["tanggal"] }}</td>
      <td class="text-right p-1">{{ $v["id"] }}</td>
      <td class="text-right p-1">{{ $v["no_pol"] }}</td>
      <td class="text-right p-1">{{ $v["lokasi"] }}</td>
      <td class="text-right p-1">{{ $v['tipe'] == 'UJ' ? $v["gaji"] : '' }}</td>
      <td class="text-right p-1">{{ $v['tipe'] == 'UJ' ? $v["makan"] : '' }}</td>
      <td class="text-right p-1">{{ $v['tipe'] == 'SB' ? $v["gaji"] : '' }}</td>
      <td class="text-right p-1">{{ $v['tipe'] == 'SB' ? $v["makan"] : '' }}</td>
      <td class="text-right p-1">{{ $v["total"] }}</td>
    </tr>
    @endforeach
    <tr>
      <td colspan="6" style="text-align: right;"> Grand Total</td>
      <td > {{$info['uj_gaji']}}</td>
      <td > {{$info['uj_makan']}}</td>
      <td > {{$info['sb_gaji']}}</td>
      <td > {{$info['sb_gaji']}}</td>
      <td > {{$info['total']}}</td>
    </tr>
  </tbody>
</table>