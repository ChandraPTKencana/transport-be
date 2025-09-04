<table class="line borderless text-center mt-2" style="font-size: x-small;">
  <thead class="text-center" style="background-color: #B0A4A4;">
    <tr>
      <th style="border: 1px solid black;">No</th>
      <th style="border: 1px solid black;">Tanggal</th>
      <th style="border: 1px solid black;">Tipe</th>
      <th style="border: 1px solid black;">Nama</th>
      <th style="border: 1px solid black;">No KTP</th>
      <th style="border: 1px solid black;">No SIM</th>
      <th style="border: 1px solid black;">Nominal</th>
      <th style="border: 1px solid black;">Note</th>
      <th style="border: 1px solid black;">Created At</th>
      <th style="border: 1px solid black;">Updated At</th>
      <th style="border: 1px solid black;">Deleted By</th>
      <th style="border: 1px solid black;">Deleted At</th>
      <th style="border: 1px solid black;">Deleted Reason</th>
    </tr>
  </thead>
  <tbody>  
    @foreach($data as $k=>$v)
    <tr>
      <td style="border: 1px solid black;">{{$loop->iteration}}</td>
      <td style="border: 1px solid black;">{{ $v["tanggal"] }}</td>
      <td style="border: 1px solid black;">{{ $v["type"] }}</td>
      <td style="border: 1px solid black;">{{ $v["employee"]?$v["employee"]["name"]:'' }}</td>
      <td style="border: 1px solid black;">{{ $v["employee"]?$v["employee"]["ktp_no"]:'' }}</td>
      <td style="border: 1px solid black;">{{ $v["employee"]?$v["employee"]["sim_no"]:'' }}</td>
      <td style="border: 1px solid black;">{{ $v["nominal"] }}</td>
      <td style="border: 1px solid black;">{{ $v["note"] }}</td>
      <td style="border: 1px solid black;">{{ $v["created_at"] }}</td>
      <td style="border: 1px solid black;">{{ $v["updated_at"] }}</td>
      <td style="border: 1px solid black;">{{ $v["cost_center_desc"] }}</td>
      <td style="border: 1px solid black;">{{ $v["deleted_at"] }}</td>
      <td style="border: 1px solid black;">{{ $v["deleted_by"] ?$v["deleted_by"]["username"] :'' }}</td>
      <td style="border: 1px solid black;">{{ $v["deleted_reason"] }}</td>
    </tr>
    @endforeach
  </tbody>
</table>
