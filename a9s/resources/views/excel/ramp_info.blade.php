<table class="line borderless text-center mt-2" style="font-size: x-small;">
  <thead class="text-center" style="background-color: #B0A4A4;">
    <tr>
      <th style="border: 1px solid black;">No</th>
      <th style="border: 1px solid black;">Ramp</th>
      <th style="border: 1px solid black;">Supir</th>
      <th style="border: 1px solid black;">Kernet</th>
      <th style="border: 1px solid black;">Gaji Supir</th>
      <th style="border: 1px solid black;">Gaji Kernet</th>
      <th style="border: 1px solid black;">U.Makan Supir</th>
      <th style="border: 1px solid black;">U.Makan Kernet</th>
      <th style="border: 1px solid black;">Tonase</th>
      <th style="border: 1px solid black;">Rata2 Tonase</th>
      <th style="border: 1px solid black;">Trip</th>
    </tr>
  </thead>
  <tbody>
    @foreach($data as $k=>$v)
    <tr>
      <td>{{$loop->iteration}}</td>
      <td>{{ $v["xto"] }}</td>
      <td class="text-right p-1">{{ $v["z_supir"] }}</td>
      <td class="text-right p-1">{{ $v["z_kernet"] }}</td>
      <td class="text-right p-1">{{ $v["z_gaji_supir"] }}</td>
      <td class="text-right p-1">{{ $v["z_gaji_kernet"] }}</td>
      <td class="text-right p-1">{{ $v["z_makan_supir"] }}</td>
      <td class="text-right p-1">{{ $v["z_makan_kernet"] }}</td>
      <td class="text-right p-1">{{ $v["tonase"] }}</td>
      <td class="text-right p-1">{{ $v["z_rt_tonase"] }}</td>
      <td class="text-right p-1">{{ $v["trip"] }}</td>
    </tr>
    @endforeach
  </tbody>
</table>