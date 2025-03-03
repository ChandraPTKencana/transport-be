<table class="line borderless text-center mt-2" style="font-size: x-small;">
      <thead class="text-center" style="background-color: #B0A4A4;">
        
      <tr>
        <th colspan="33" style="text-align: center; font-weight:bold;">
          Laporan Uang Gaji Dan Makan Standby,Uang Gaji Dan Makan Trip, Potongan, Serta Bonus Periode {{$info['periode']}}
        </th>
      </tr>
      <tr>
          <th rowspan="2" style="border: 1px solid black; font-weight:bold;">No</th>
          <th rowspan="2" style="border: 1px solid black; font-weight:bold;">ID</th>
          <th rowspan="2" style="border: 1px solid black; font-weight:bold;">Nama Pekerja</th>
          <th rowspan="2" style="border: 1px solid black; font-weight:bold;">Jabatan</th>
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
          <th rowspan="2" style="border: 1px solid black; font-weight:bold;">Potongan 1</th>
          <th rowspan="2" style="border: 1px solid black; font-weight:bold;">Ttl Periode 1</th>

          <th colspan="3" style="border: 1px solid black; font-weight:bold;">Standby 2</th>
          <th rowspan="2" style="border: 1px solid black; font-weight:bold;">Potongan 2</th>
          <th rowspan="2" style="border: 1px solid black; font-weight:bold;">U.Kerajinan</th>
          <th rowspan="2" style="border: 1px solid black; font-weight:bold;">Ttl Periode 2</th>
          <th rowspan="2" style="border: 1px solid black; font-weight:bold;">Ttl Periode 1+2</th>

          <th colspan="3" style="border: 1px solid black; font-weight:bold;">Trip</th>
          <th rowspan="2" style="border: 1px solid black; font-weight:bold;">Potongan Trip</th>
          <th rowspan="2" style="border: 1px solid black; font-weight:bold;">Total</th>
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
          <th style="border: 1px solid black; font-weight:bold;">Gaji</th>
          <th style="border: 1px solid black; font-weight:bold;">Makan</th>
          <th style="border: 1px solid black; font-weight:bold;">Dinas</th>
        </tr>
      </thead>
      <tbody>
        @foreach($data as $k=>$v)
        @php
          $ktp_no=mb_strtoupper("'".$v["employee_ktp_no"],'UTF-8');
          $row_jump = 3;
        @endphp
        <tr>
          <td style="border: 1px solid black;">{{$loop->iteration}}</td>
          <td style="border: 1px solid black;">{{ $v["employee_id"] }}</td>
          <td style="border: 1px solid black;">{{ $v["employee_name"] }}</td>
          <td style="border: 1px solid black;">{{ $v["employee_role"] }}</td>
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
          <td style="border: 1px solid black;" class="text-right p-1">{{ $v["salary_bonus_nominal"] }}</td>
          <td style="border: 1px solid black; font-weight:bold;" class="text-right p-1">=N{{$loop->iteration+$row_jump}}+O{{$loop->iteration+$row_jump}}+P{{$loop->iteration+$row_jump}}+Q{{$loop->iteration+$row_jump}}</td>
          <td style="border: 1px solid black;" class="text-right p-1">{{ $v["sb_gaji_2"] }}</td>
          <td style="border: 1px solid black;" class="text-right p-1">{{ $v["sb_makan_2"] }}</td>
          <td style="border: 1px solid black;" class="text-right p-1">{{ $v["sb_dinas_2"] }}</td>
          <td style="border: 1px solid black;" class="text-right p-1">{{ $v["salary_bonus_nominal_2"] }}</td>
          <td style="border: 1px solid black;" class="text-right p-1">{{ $v["kerajinan"] }}</td>
          <td style="border: 1px solid black; font-weight:bold;" class="text-right p-1">=S{{$loop->iteration+$row_jump}}+T{{$loop->iteration+$row_jump}}+U{{$loop->iteration+$row_jump}}+V{{$loop->iteration+$row_jump}}+W{{$loop->iteration+$row_jump}}</td>
          <td style="border: 1px solid black; font-weight:bold;" class="text-right p-1">=R{{$loop->iteration+$row_jump}}+X{{$loop->iteration+$row_jump}}</td>
          <td style="border: 1px solid black;" class="text-right p-1">{{ $v["uj_gaji"] }}</td>
          <td style="border: 1px solid black;" class="text-right p-1">{{ $v["uj_makan"] }}</td>
          <td style="border: 1px solid black;" class="text-right p-1">{{ $v["uj_dinas"] }}</td>
          <td style="border: 1px solid black;" class="text-right p-1">{{ $v["nominal_cut"] }}</td>
          <td style="border: 1px solid black; font-weight:bold;" class="text-right p-1">=Y{{$loop->iteration+$row_jump}}+Z{{$loop->iteration+$row_jump}}+AA{{$loop->iteration+$row_jump}}+AB{{$loop->iteration+$row_jump}}-AC{{$loop->iteration+$row_jump}}</td>
          <td style="border: 1px solid black;" class="text-right p-1">{{ $v["employee_bpjs_kesehatan"] }}</td>
          <td style="border: 1px solid black;" class="text-right p-1">{{ $v["employee_bpjs_jamsos"] }}</td>
          <td style="border: 1px solid black; font-weight:bold;" class="text-right p-1">=AD{{$loop->iteration+$row_jump}}-AE{{$loop->iteration+$row_jump}}-AF{{$loop->iteration+$row_jump}}</td>
        </tr>
        @endforeach
        <tr>
          <td colspan="13" style="text-align: right; border: 1px solid black; font-weight:bold;"> Grand Total</td>
          <td style="border: 1px solid black; font-weight:bold;" class="p-1" > =SUM(N4:N{{ count($data) + $row_jump }})</td>
          <td style="border: 1px solid black; font-weight:bold;" class="p-1" > =SUM(O4:O{{ count($data) + $row_jump }})</td>
          <td style="border: 1px solid black; font-weight:bold;" class="p-1" > =SUM(P4:P{{ count($data) + $row_jump }})</td>
          <td style="border: 1px solid black; font-weight:bold;" class="p-1" > =SUM(Q4:Q{{ count($data) + $row_jump }})</td>
          <td style="border: 1px solid black; font-weight:bold;" class="p-1" > =SUM(R4:R{{ count($data) + $row_jump }})</td>
          <td style="border: 1px solid black; font-weight:bold;" class="p-1" > =SUM(S4:S{{ count($data) + $row_jump }})</td>
          <td style="border: 1px solid black; font-weight:bold;" class="p-1" > =SUM(T4:T{{ count($data) + $row_jump }})</td>
          <td style="border: 1px solid black; font-weight:bold;" class="p-1" > =SUM(U4:U{{ count($data) + $row_jump }})</td>
          <td style="border: 1px solid black; font-weight:bold;" class="p-1" > =SUM(V4:V{{ count($data) + $row_jump }})</td>
          <td style="border: 1px solid black; font-weight:bold;" class="p-1" > =SUM(W4:W{{ count($data) + $row_jump }})</td>
          <td style="border: 1px solid black; font-weight:bold;" class="p-1" > =SUM(X4:X{{ count($data) + $row_jump }})</td>
          <td style="border: 1px solid black; font-weight:bold;" class="p-1" > =SUM(Y4:Y{{ count($data) + $row_jump }})</td>
          <td style="border: 1px solid black; font-weight:bold;" class="p-1" > =SUM(Z4:Z{{ count($data) + $row_jump }})</td>
          <td style="border: 1px solid black; font-weight:bold;" class="p-1" > =SUM(AA4:AA{{ count($data) + $row_jump }})</td>
          <td style="border: 1px solid black; font-weight:bold;" class="p-1" > =SUM(AB4:AB{{ count($data) + $row_jump }})</td>
          <td style="border: 1px solid black; font-weight:bold;" class="p-1" > =SUM(AC4:AC{{ count($data) + $row_jump }})</td>
          <td style="border: 1px solid black; font-weight:bold;" class="p-1" > =SUM(AD4:AD{{ count($data) + $row_jump }}) </td>
          <td style="border: 1px solid black; font-weight:bold;" class="p-1" > =SUM(AE4:AE{{ count($data) + $row_jump }})</td>
          <td style="border: 1px solid black; font-weight:bold;" class="p-1" > =SUM(AF4:AF{{ count($data) + $row_jump }}) </td>
          <td style="border: 1px solid black; font-weight:bold;" class="p-1" > =SUM(AG4:AG{{ count($data) + $row_jump }})</td>
        </tr>
      </tbody>
    </table>