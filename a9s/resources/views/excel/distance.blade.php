<table class="line borderless text-center mt-2" style="font-size: x-small;">
  <thead class="text-center" style="background-color: #B0A4A4;">
    <tr>
      <th style="border: 1px solid black;">No</th>
      <th style="border: 1px solid black;">NoPol</th>
      <th style="border: 1px solid black;">Ttl Jarak</th>
      <th style="border: 1px solid black;">Ttl Biaya Supir</th>
      <th style="border: 1px solid black;">Ttl Biaya Kernet</th>
      <th style="border: 1px solid black;">Ttl Biaya Solar</th>
      <th style="border: 1px solid black;">Ttl Biaya Operasional</th>
      <th style="border: 1px solid black;">Ttl Biaya Lainnya</th>
      <th style="border: 1px solid black;">Ttl Extra Money</th>
      <th style="border: 1px solid black;">Tonase</th>
    </tr>
  </thead>
  <tbody>
    @foreach($data as $k=>$v)
    @php
    $row_jump = 1;
    @endphp
    <tr>
      <td>{{$loop->iteration}}</td>
      <td>{{ $v["no_pol"] }}</td>
      <td class="text-right p-1">{{ $v["distance"] }}</td>
      <td class="text-right p-1">{{ $v["supir"] }}</td>
      <td class="text-right p-1">{{ $v["kernet"] }}</td>
      <td class="text-right p-1">{{ $v["solar"] }}</td>
      <td class="text-right p-1">{{ $v["operasional"] }}</td>
      <td class="text-right p-1">{{ $v["lainnya"] }}</td>
      <td class="text-right p-1">{{ $v["extra_money"] }}</td>
      <td class="text-right p-1">{{ $v["tonase"] }}</td>
    </tr>
    <tr>
      <td colspan="2" style="border:none;"></td>
      <td class="text-right p-1">=SUM(C{{$row_jump + 1}}:C{{ count($data) + $row_jump }})</td>
      <td class="text-right p-1">=SUM(D{{$row_jump + 1}}:D{{ count($data) + $row_jump }})</td>
      <td class="text-right p-1">=SUM(E{{$row_jump + 1}}:E{{ count($data) + $row_jump }})</td>
      <td class="text-right p-1">=SUM(F{{$row_jump + 1}}:F{{ count($data) + $row_jump }})</td>
      <td class="text-right p-1">=SUM(G{{$row_jump + 1}}:G{{ count($data) + $row_jump }})</td>
      <td class="text-right p-1">=SUM(H{{$row_jump + 1}}:H{{ count($data) + $row_jump }})</td>
      <td class="text-right p-1">=SUM(I{{$row_jump + 1}}:I{{ count($data) + $row_jump }})</td>
      <td class="text-right p-1">=SUM(J{{$row_jump + 1}}:J{{ count($data) + $row_jump }})</td>
    </tr>
    @endforeach
  </tbody>
</table>