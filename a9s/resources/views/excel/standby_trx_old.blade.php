<table class="line borderless text-center mt-2" style="font-size: x-small;">
  @foreach($data as $kdt=>$vdt)
    <tr>
      <th style="border: 1px solid black; background-color: #cccccc;"> ID </th>
      <th style="border: 1px solid black; background-color: #cccccc;"> Trx Trp ID </th>
      <th style="border: 1px solid black; background-color: #cccccc;"> Nama Supir </th>
      <th style="border: 1px solid black; background-color: #cccccc;"> Nama Kernet </th>
      <th style="border: 1px solid black; background-color: #cccccc;"> No pol </th>
      <th style="border: 1px solid black; background-color: #cccccc;"> Tujuan </th>
      <th style="border: 1px solid black; background-color: #cccccc;"> Note For Remarks </th>
      <th style="border: 1px solid black; background-color: #cccccc;"> Target Peralihan  </th>
      <th style="border: 1px solid black; background-color: #cccccc;"> Tipe Peralihan </th>
    </tr>
    <tr>
      <td style="border: 1px solid black;"> {{ $vdt["id"] }} </td>
      <td style="border: 1px solid black;"> {{ $vdt["trx_trp_id"] }} </td>
      <td style="border: 1px solid black;"> {{ $vdt["supir"] }} #{{$vdt["supir_id"]}} </td>
      <td style="border: 1px solid black;"> {{ $vdt["kernet"] }} #{{$vdt["kernet_id"]}} </td>
      <td style="border: 1px solid black;"> {{ $vdt["no_pol"] }} </td>
      <td style="border: 1px solid black;"> {{ $vdt["xto"] }} </td>
      <td style="border: 1px solid black;"> {{ $vdt["note_for_remarks"] }} </td>
      <td style="border: 1px solid black;"> {{ $vdt["transition_target"] }} </td>
      <td style="border: 1px solid black;"> {{ $vdt["transition_type"] }} </td>
    </tr>
    <tr>
      <th colspan="9"></th>
    </tr>
    <tr>
      <th colspan="4" style="border: 1px solid black; background-color:#a1a1a1; font-weight:bold;"> Standby Mst </th>
    </tr>
    <tr>
      <th style="border: 1px solid black; background-color: #cccccc;"> ID </th>
      <th style="border: 1px solid black; background-color: #cccccc;"> Name </th>
      <th style="border: 1px solid black; background-color: #cccccc;"> Type </th>
      <th style="border: 1px solid black; background-color: #cccccc;"> Amount </th>
    </tr>
    <tr>
      <td style="border: 1px solid black;"> {{ $vdt["standby_mst_"]["id"] }} </td>
      <td style="border: 1px solid black;"> {{ $vdt["standby_mst_"]["name"] }} </td>
      <td style="border: 1px solid black;"> {{ $vdt["standby_mst_"]["tipe"] }} </td>
      <td style="border: 1px solid black;"> {{ $vdt["standby_mst_"]["amount"] }} </td>
    </tr>
    <tr>
      <th colspan="9"></th>
    </tr>
    <tr>
      <th colspan="5" style="border: 1px solid black; background-color:#a1a1a1; font-weight:bold;"> Details </th>
    </tr>
    <tr>
      <td style="border: 1px solid black; background-color: #cccccc;">No</td>
      <td style="border: 1px solid black; background-color: #cccccc;">Tanggal</td>
      <td style="border: 1px solid black; background-color: #cccccc;">Waktu</td>
      <td style="border: 1px solid black; background-color: #cccccc;">Dibayarkan</td>
      <td style="border: 1px solid black; background-color: #cccccc;">Ket</td>
    </tr>

    @foreach($vdt['details'] as $k=>$v)
    <tr>
      <td style="border: 1px solid black;">{{$loop->iteration}}</td>
      <td style="border: 1px solid black;">{{ $v["tanggal"] }}</td>
      <td style="border: 1px solid black;">{{ $v["waktu"] }}</td>
      <td style="border: 1px solid black;">{{ $v["be_paid"] ? 'Ya' : 'Tidak' }}</td>
      <td style="border: 1px solid black;">{{ $v["note"]  }}</td>
    </tr>
    @endforeach
    <tr>
      <th colspan="9"></th>
    </tr>
    <tr>
      <th colspan="9" style="background-color: #000000;"></th>
    </tr>
    <tr>
      <th colspan="9"></th>
    </tr>
  @endforeach
</table>
