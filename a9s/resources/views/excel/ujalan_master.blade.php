<table class="line borderless text-center mt-2" style="font-size: x-small;">
  <tr>
    <th style="border: 1px solid black; background-color: #cccccc;"> ID </th>
    <th style="border: 1px solid black; background-color: #cccccc;"> JENIS </th>
    <th style="border: 1px solid black; background-color: #cccccc;"> INFO </th>
    <th style="border: 1px solid black; background-color: #cccccc;"> BIAYA </th>
  </tr>
  <tr>
    <td style="border: 1px solid black;"> {{ $data["id"] }} </td>
    <td style="border: 1px solid black;"> {{ $data["jenis"] }} </td>
    <td style="border: 1px solid black;"> {{ $data["asst_opt"] }} </td>
    <td style="border: 1px solid black;"> {{ $data["harga"] }} </td>
  </tr>
  <tr>
    <th colspan="9"></th>
  </tr>
  <tr>
    <th colspan="4" style="border: 1px solid black; background-color: #cccccc;"> To </th>
  </tr>
  <tr>
    <td colspan="4" style="border: 1px solid black;"> {{ $data["xto"] }} </td>
  </tr>
  <tr>
    <th colspan="9"></th>
  </tr>
  <tr>
    <th colspan="4" style="border: 1px solid black; background-color: #cccccc;"> Tipe </th>
  </tr>
  <tr>
    <td colspan="4" style="border: 1px solid black;"> {{ $data["tipe"] }} </td>
  </tr>
  <tr>
    <th colspan="9"></th>
  </tr>
  <tr>
    <th style="border: 1px solid black; background-color: #cccccc;"> Bonus Trip Supir </th>
    <th style="border: 1px solid black; background-color: #cccccc;"> Bonus Trip Kernet </th>
    <th style="border: 1px solid black; background-color: #cccccc;"> KM Range </th>
    <th style="border: 1px solid black; background-color: #cccccc;"> Asal Peralihan </th>
  </tr>
  <tr>
    <td style="border: 1px solid black;"> {{ $data["bonus_trip_supir"] }} </td>
    <td style="border: 1px solid black;"> {{ $data["bonus_trip_kernet"] }} </td>
    <td style="border: 1px solid black;"> {{ $data["km_range"] }} </td>
    <td style="border: 1px solid black;"> {{ $data["transition_from"] }} </td>
  </tr>
  <tr>
    <th colspan="9"></th>
  </tr>
  <tr>
    <th colspan="5" style="border: 1px solid black; background-color:#a1a1a1; font-weight:bold;"> Detail Uang Jalan </th>
  </tr>
  <tr>
    <td style="border: 1px solid black; background-color: #cccccc;">No</td>
    <td style="border: 1px solid black; background-color: #cccccc;">Desc</td>
    <td style="border: 1px solid black; background-color: #cccccc;">Harga @</td>
    <td style="border: 1px solid black; background-color: #cccccc;">Qty</td>
    <td style="border: 1px solid black; background-color: #cccccc;">Total</td>
  </tr>

  @foreach($data['details'] as $k=>$v)
  <tr>
    <td style="border: 1px solid black;">{{$loop->iteration}}</td>
    <td style="border: 1px solid black;">{{ $v["xdesc"] }}</td>
    <td style="border: 1px solid black;">{{ $v["harga"] }}</td>
    <td style="border: 1px solid black;">{{ $v["qty"] }}</td>
    <td style="border: 1px solid black;">{{ $v["harga"] * $v["qty"]  }}</td>
  </tr>
  @endforeach
  <tr>
    <th colspan="9"></th>
  </tr>
  <tr>
    <th colspan="9" style="border: 1px solid black; background-color:#a1a1a1; font-weight:bold;"> Detail PVR</th>
  </tr>
  <tr>
    <td  style="border: 1px solid black; background-color: #cccccc;">No</td>
    <td  style="border: 1px solid black; background-color: #cccccc;">ACC ID</td>
    <td  style="border: 1px solid black; background-color: #cccccc;">ACC CODE</td>
    <td  style="border: 1px solid black; background-color: #cccccc;">ACC NAME</td>
    <td  style="border: 1px solid black; background-color: #cccccc;">DESC</td>
    <td  style="border: 1px solid black; background-color: #cccccc;">Amount</td>
    <td  style="border: 1px solid black; background-color: #cccccc;">Qty</td>
    <td  style="border: 1px solid black; background-color: #cccccc;">For</td>
    <td  style="border: 1px solid black; background-color: #cccccc;">Total</td>
  </tr>

  @foreach($data['details2'] as $k=>$v)
  <tr>
    <td style="border: 1px solid black;">{{$loop->iteration}}</td>
    <td style="border: 1px solid black;">{{ $v["ac_account_id"] }}</td>
    <td style="border: 1px solid black;">{{ $v["ac_account_code"] }}</td>
    <td style="border: 1px solid black;">{{ $v["ac_account_name"] }}</td>
    <td style="border: 1px solid black;">{{ $v["description"] }}</td>
    <td style="border: 1px solid black;">{{ $v["amount"] }}</td>
    <td style="border: 1px solid black;">{{ $v["qty"] }}</td>
    <td style="border: 1px solid black;">{{ $v["xfor"] }}</td>
    <td style="border: 1px solid black;">{{ $v["amount"] * $v["qty"]  }}</td>
  </tr>
  @endforeach
</table>
