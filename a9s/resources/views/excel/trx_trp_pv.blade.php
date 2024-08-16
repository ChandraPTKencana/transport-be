<table class="line borderless text-center mt-2" style="font-size: x-small;">
  <thead class="text-center" style="background-color: #B0A4A4;">

    <tr>
      <th rowspan="2" style="border: 1px solid black;">No</th>
      <th rowspan="2" style="border: 1px solid black;">ID</th>
      <th rowspan="2" style="border: 1px solid black;">Tanggal</th>
      <th rowspan="2" style="border: 1px solid black;">No Pol</th>
      <th rowspan="2" style="border: 1px solid black;">Jenis</th>
      <th rowspan="2" style="border: 1px solid black;">Tujuan</th>
      <th colspan="5" style="border: 1px solid black;">Biaya</th>
    </tr>
    <tr>      
      <th>Ujalan</th>
      <th>PV Total</th>
      <th>PV Date</th>
      <th>PV No</th>
      <th>PVR No</th>
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

      <td>{{ $v["amount"] }}</td>
      <td>{{ $v["pv_total"] }}</td>
      <td>{{ $v["pv_datetime"] }}</td>
      <td>{{ $v["pv_no"] }}</td>
      <td>{{ $v["pvr_no"] }}</td>
    </tr>
    @endforeach
  </tbody>
</table>
