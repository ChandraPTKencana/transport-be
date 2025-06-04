<table class="line borderless text-center mt-2" style="font-size: x-small;">
      <thead class="text-center" style="background-color: #B0A4A4;">   
      <tr>
        <th colspan="41" style="text-align: center; font-weight:bold;">
          Laporan Uang Gaji Dan Makan Standby,Uang Gaji Dan Makan Trip, Potongan, Serta Bonus Periode {{$info['periode']}}
        </th>
      </tr>
      <tr>
          <th rowspan="2" style="border: 1px solid black; font-weight:bold;">No</th>
          <th rowspan="2" style="border: 1px solid black; font-weight:bold;">ID</th>
          <th rowspan="2" style="border: 1px solid black; font-weight:bold;">Nama Pekerja</th>
          <th rowspan="2" style="border: 1px solid black; font-weight:bold;">Jabatan</th>
          <th rowspan="2" style="border: 1px solid black; font-weight:bold;">Agama</th>
          <th rowspan="2" style="border: 1px solid black; font-weight:bold;">Tmpt Lahir</th>
          <th rowspan="2" style="border: 1px solid black; font-weight:bold;">Tgl Lahir</th>
          <th rowspan="2" style="border: 1px solid black; font-weight:bold;">TMK</th>
          <th rowspan="2" style="border: 1px solid black; font-weight:bold;">No KTP</th>
          <th rowspan="2" style="border: 1px solid black; font-weight:bold;">Alamat</th>
          <th rowspan="2" style="border: 1px solid black; font-weight:bold;">Status</th>
          <th rowspan="2" style="border: 1px solid black; font-weight:bold;">No Rek</th>
          <th rowspan="2" style="border: 1px solid black; font-weight:bold;">Nama Rek</th>
          <th rowspan="2" style="border: 1px solid black; font-weight:bold;">Nama Bank</th>
          <th colspan="3" style="border: 1px solid black; font-weight:bold;">Standby 1</th>
          <th rowspan="2" style="border: 1px solid black; font-weight:bold;">Ttl Periode 1</th>
          <th colspan="3" style="border: 1px solid black; font-weight:bold;">Standby 2</th>
          <th rowspan="2" style="border: 1px solid black; font-weight:bold;">U.Kerajinan</th>
          <th colspan="3" style="border: 1px solid black; font-weight:bold;">Bonus Trip</th>
          <th rowspan="2" style="border: 1px solid black; font-weight:bold;">Ttl Periode 2</th>
          <th rowspan="2" style="border: 1px solid black; font-weight:bold;">Ttl Periode 1+2</th>
          <th colspan="3" style="border: 1px solid black; font-weight:bold;">Trip</th>
          <th colspan="4" style="border: 1px solid black; font-weight:bold;">Trip Lain</th>
          <th colspan="3" style="border: 1px solid black; font-weight:bold;">Trip Tunggu</th>
          <th rowspan="2" style="border: 1px solid black; font-weight:bold;">Potongan Trip</th>
          <th rowspan="2" style="border: 1px solid black; font-weight:bold;">Total</th>
          <!-- <th rowspan="2" style="border: 1px solid black; font-weight:bold;">Potongan Lain</th> -->
          <th rowspan="2" style="border: 1px solid black; font-weight:bold;">BPJS Kesehatan</th>
          <th rowspan="2" style="border: 1px solid black; font-weight:bold;">BPJS JAMSOS</th>
          <th rowspan="2" style="border: 1px solid black; font-weight:bold;">Grand Total</th>
        </tr>
        <tr>
          <th style="border: 1px solid black; font-weight:bold;">Gaji</th>
          <th style="border: 1px solid black; font-weight:bold;">Makan</th>
          <th style="border: 1px solid black; font-weight:bold;">Dinas</th>
          <th style="border: 1px solid black; font-weight:bold;">Gaji</th>
          <th style="border: 1px solid black; font-weight:bold;">Makan</th>
          <th style="border: 1px solid black; font-weight:bold;">Dinas</th>
          <th style="border: 1px solid black; font-weight:bold;">Jmlh</th>
          <th style="border: 1px solid black; font-weight:bold;">Gaji</th>
          <th style="border: 1px solid black; font-weight:bold;">Dinas</th>
          <th style="border: 1px solid black; font-weight:bold;">Gaji</th>
          <th style="border: 1px solid black; font-weight:bold;">Makan</th>
          <th style="border: 1px solid black; font-weight:bold;">Dinas</th>
          <th style="border: 1px solid black; font-weight:bold;">Jmlh</th>
          <th style="border: 1px solid black; font-weight:bold;">Gaji</th>
          <th style="border: 1px solid black; font-weight:bold;">Makan</th>
          <th style="border: 1px solid black; font-weight:bold;">Dinas</th>
          <th style="border: 1px solid black; font-weight:bold;">Jmlh</th>
          <th style="border: 1px solid black; font-weight:bold;">Gaji</th>
          <th style="border: 1px solid black; font-weight:bold;">Dinas</th>
        </tr>
      </thead>
      <tbody>
        @foreach($data as $k=>$v)
        @php
          $ktp_no=mb_strtoupper("'".$v["employee_ktp_no"],'UTF-8');
          $row_jump = 3;

          $bonus_jumlah = $v["trip_cpo"]+$v["trip_pk"]+$v["trip_tbs"]+$v["trip_tbsk"];
        @endphp
        <tr>
          <td style="border: 1px solid black;">{{$loop->iteration}}</td>
          <td style="border: 1px solid black;">{{ $v["employee_id"] }}</td>
          <td style="border: 1px solid black;">{{ $v["employee_name"] }}</td>
          <td style="border: 1px solid black;">{{ $v["employee_role"] }}</td>
          <td style="border: 1px solid black;">{{ $v["employee_religion"] }}</td>
          <td style="border: 1px solid black;" class="text-right p-1">{{ $v["employee_birth_place"] }}</td>
          <td style="border: 1px solid black;" class="text-right p-1">{{ $v["employee_birth_date"] ? date("d-m-Y",strtotime($v["employee_birth_date"])) : '' }}</td>
          <td style="border: 1px solid black;" class="text-right p-1">{{ $v["employee_tmk"] ? date("d-m-Y",strtotime($v["employee_tmk"])) : '' }}</td>
          <td style="border: 1px solid black;" class="text-right p-1">{{ $ktp_no }}</td>
          <td style="border: 1px solid black;" class="text-right p-1">{{ $v["employee_address"]}}</td>
          <td style="border: 1px solid black;" class="text-right p-1">{{ $v["employee_status"]}}</td>
          <td style="border: 1px solid black;" class="text-right p-1">{{ $v["employee_rek_no"]}}</td>
          <td style="border: 1px solid black;" class="text-right p-1">{{ $v["employee_rek_name"]}}</td>
          <td style="border: 1px solid black;" class="text-right p-1">{{ $v["employee_bank_name"]}}</td>
          <td style="border: 1px solid black;" class="text-right p-1">{{ $v["sb_gaji"] }}</td>
          <td style="border: 1px solid black;" class="text-right p-1">{{ $v["sb_makan"] }}</td>
          <td style="border: 1px solid black;" class="text-right p-1">{{ $v["sb_dinas"] }}</td>
          <td style="border: 1px solid black; font-weight:bold;" class="p-1" >=O{{$loop->iteration+$row_jump}}+P{{$loop->iteration+$row_jump}}+Q{{$loop->iteration+$row_jump}}</td>
          <td style="border: 1px solid black;" class="text-right p-1">{{ $v["sb_gaji_2"] }}</td>
          <td style="border: 1px solid black;" class="text-right p-1">{{ $v["sb_makan_2"] }}</td>
          <td style="border: 1px solid black;" class="text-right p-1">{{ $v["sb_dinas_2"] }}</td>
          <td style="border: 1px solid black;" class="text-right p-1">{{ $v["kerajinan"] }}</td>
          <td style="border: 1px solid black;" class="text-right p-1">{{ $bonus_jumlah }}</td>
          <td style="border: 1px solid black;" class="text-right p-1">{{ $v["bonus_gaji"] }}</td> 
          <td style="border: 1px solid black;" class="text-right p-1">{{ $v["bonus_dinas"] }}</td> 
          <td style="border: 1px solid black; font-weight:bold;" class="p-1" > =S{{$loop->iteration+$row_jump}}+T{{$loop->iteration+$row_jump}}+U{{$loop->iteration+$row_jump}}+V{{$loop->iteration+$row_jump}}+X{{$loop->iteration+$row_jump}}+Y{{$loop->iteration+$row_jump}}</td>
          <td style="border: 1px solid black; font-weight:bold;" class="p-1" > =R{{$loop->iteration+$row_jump}}+Z{{$loop->iteration+$row_jump}}</td>
          <td style="border: 1px solid black;" class="text-right p-1">{{ $v["uj_gaji"] }}</td>
          <td style="border: 1px solid black;" class="text-right p-1">{{ $v["uj_makan"] }}</td>
          <td style="border: 1px solid black;" class="text-right p-1">{{ $v["uj_dinas"] }}</td>
          <td style="border: 1px solid black;" class="text-right p-1">{{ $v["trip_lain"] }}</td>
          <td style="border: 1px solid black;" class="text-right p-1">{{ $v["trip_lain_gaji"] }}</td> 
          <td style="border: 1px solid black;" class="text-right p-1">{{ $v["trip_lain_makan"] }}</td> 
          <td style="border: 1px solid black;" class="text-right p-1">{{ $v["trip_lain_dinas"] }}</td> 
          <td style="border: 1px solid black;" class="text-right p-1">{{ $v["trip_tunggu"] }}</td>
          <td style="border: 1px solid black;" class="text-right p-1">{{ $v["trip_tunggu_gaji"] }}</td>
          <td style="border: 1px solid black;" class="text-right p-1">{{ $v["trip_tunggu_dinas"] }}</td> 
          <td style="border: 1px solid black;" class="text-right p-1">{{ $v["nominal_cut"] }}</td> 
          <td style="border: 1px solid black; font-weight:bold;" class="text-right p-1">=AB{{$loop->iteration+$row_jump}}+AC{{$loop->iteration+$row_jump}}+AD{{$loop->iteration+$row_jump}}+AF{{$loop->iteration+$row_jump}}
          +AG{{$loop->iteration+$row_jump}}+AH{{$loop->iteration+$row_jump}}+AJ{{$loop->iteration+$row_jump}}+AK{{$loop->iteration+$row_jump}}-AL{{$loop->iteration+$row_jump}}
          </td>
          <td style="border: 1px solid black;" class="text-right p-1">{{ $v["employee_bpjs_kesehatan"] }}</td>
          <td style="border: 1px solid black;" class="text-right p-1">{{ $v["employee_bpjs_jamsos"] }}</td>
          <td style="border: 1px solid black; font-weight:bold;" class="text-right p-1">=AM{{$loop->iteration+$row_jump}}-AN{{$loop->iteration+$row_jump}}-AO{{$loop->iteration+$row_jump}}</td>
        </tr>
        @endforeach
        <tr>
          <td colspan="14" style="text-align: right; border: 1px solid black; font-weight:bold;"> Grand Total</td>
          <td style="border: 1px solid black; font-weight:bold;" class="p-1" > =SUM(O{{ $row_jump + 1 }}:O{{ count($data) + $row_jump }})</td>
          <td style="border: 1px solid black; font-weight:bold;" class="p-1" > =SUM(P{{ $row_jump + 1 }}:P{{ count($data) + $row_jump }})</td>
          <td style="border: 1px solid black; font-weight:bold;" class="p-1" > =SUM(Q{{ $row_jump + 1 }}:Q{{ count($data) + $row_jump }})</td>
          <td style="border: 1px solid black; font-weight:bold;" class="p-1" > =SUM(R{{ $row_jump + 1 }}:R{{ count($data) + $row_jump }})</td>
          <td style="border: 1px solid black; font-weight:bold;" class="p-1" > =SUM(S{{ $row_jump + 1 }}:S{{ count($data) + $row_jump }})</td>
          <td style="border: 1px solid black; font-weight:bold;" class="p-1" > =SUM(T{{ $row_jump + 1 }}:T{{ count($data) + $row_jump }})</td>
          <td style="border: 1px solid black; font-weight:bold;" class="p-1" > =SUM(U{{ $row_jump + 1 }}:U{{ count($data) + $row_jump }})</td>
          <td style="border: 1px solid black; font-weight:bold;" class="p-1" > =SUM(V{{ $row_jump + 1 }}:V{{ count($data) + $row_jump }})</td>
          <td style="border: 1px solid black; font-weight:bold;" class="p-1" > =SUM(W{{ $row_jump + 1 }}:W{{ count($data) + $row_jump }})</td>
          <td style="border: 1px solid black; font-weight:bold;" class="p-1" > =SUM(X{{ $row_jump + 1 }}:X{{ count($data) + $row_jump }})</td>
          <td style="border: 1px solid black; font-weight:bold;" class="p-1" > =SUM(Y{{ $row_jump + 1 }}:Y{{ count($data) + $row_jump }})</td>
          <td style="border: 1px solid black; font-weight:bold;" class="p-1" > =SUM(Z{{ $row_jump + 1 }}:Z{{ count($data) + $row_jump }})</td>
          <td style="border: 1px solid black; font-weight:bold;" class="p-1" > =SUM(AA{{ $row_jump + 1 }}:AA{{ count($data) + $row_jump }})</td>
          <td style="border: 1px solid black; font-weight:bold;" class="p-1" > =SUM(AB{{ $row_jump + 1 }}:AB{{ count($data) + $row_jump }})</td>
          <td style="border: 1px solid black; font-weight:bold;" class="p-1" > =SUM(AC{{ $row_jump + 1 }}:AC{{ count($data) + $row_jump }})</td>
          <td style="border: 1px solid black; font-weight:bold;" class="p-1" > =SUM(AD{{ $row_jump + 1 }}:AD{{ count($data) + $row_jump }}) </td>
          <td style="border: 1px solid black; font-weight:bold;" class="p-1" > =SUM(AE{{ $row_jump + 1 }}:AE{{ count($data) + $row_jump }})</td>
          <td style="border: 1px solid black; font-weight:bold;" class="p-1" > =SUM(AF{{ $row_jump + 1 }}:AF{{ count($data) + $row_jump }})</td>
          <td style="border: 1px solid black; font-weight:bold;" class="p-1" > =SUM(AG{{ $row_jump + 1 }}:AG{{ count($data) + $row_jump }})</td>
          <td style="border: 1px solid black; font-weight:bold;" class="p-1" > =SUM(AH{{ $row_jump + 1 }}:AH{{ count($data) + $row_jump }})</td>
          <td style="border: 1px solid black; font-weight:bold;" class="p-1" > =SUM(AI{{ $row_jump + 1 }}:AI{{ count($data) + $row_jump }})</td>
          <td style="border: 1px solid black; font-weight:bold;" class="p-1" > =SUM(AJ{{ $row_jump + 1 }}:AJ{{ count($data) + $row_jump }})</td>
          <td style="border: 1px solid black; font-weight:bold;" class="p-1" > =SUM(AK{{ $row_jump + 1 }}:AK{{ count($data) + $row_jump }})</td>
          <td style="border: 1px solid black; font-weight:bold;" class="p-1" > =SUM(AL{{ $row_jump + 1 }}:AL{{ count($data) + $row_jump }})</td>
          <td style="border: 1px solid black; font-weight:bold;" class="p-1" > =SUM(AM{{ $row_jump + 1 }}:AM{{ count($data) + $row_jump }})</td>
          <td style="border: 1px solid black; font-weight:bold;" class="p-1" > =SUM(AN{{ $row_jump + 1 }}:AN{{ count($data) + $row_jump }})</td>
          <td style="border: 1px solid black; font-weight:bold;" class="p-1" > =SUM(AO{{ $row_jump + 1 }}:AO{{ count($data) + $row_jump }})</td>
          <td style="border: 1px solid black; font-weight:bold;" class="p-1" > =SUM(AP{{ $row_jump + 1 }}:AP{{ count($data) + $row_jump }})</td>
        </tr>
      </tbody>
    </table>