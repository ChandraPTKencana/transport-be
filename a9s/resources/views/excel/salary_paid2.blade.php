<table class="line borderless text-center mt-2" style="font-size: x-small;">
      <thead class="text-center" style="background-color: #B0A4A4;">
        
      <tr>
        <th colspan="11" style="text-align: center; font-weight:bold;">
          Laporan Uang Gaji Dan Makan Standby Serta Bonus Periode {{$info['periode']}}
        </th>
      </tr>
      <tr>
          <th style="border: 1px solid black; font-weight:bold;">No</th>
          <th style="border: 1px solid black; font-weight:bold;">Jabatan</th>
          <th style="border: 1px solid black; font-weight:bold;">Nama Pekerja</th>
          <th style="border: 1px solid black; font-weight:bold;">No KTP</th>
          <th style="border: 1px solid black; font-weight:bold;">Rek No</th>
          <th style="border: 1px solid black; font-weight:bold;">Rek Name</th>
          <th style="border: 1px solid black; font-weight:bold;">Bank Name</th>
          <th style="border: 1px solid black; font-weight:bold;">SB.Gaji</th>
          <th style="border: 1px solid black; font-weight:bold;">SB.Makan</th>
          <th style="border: 1px solid black; font-weight:bold;">SB.Dinas</th>
          <!-- <th style="border: 1px solid black; font-weight:bold;">Nominal Bonus</th> -->
          <th style="border: 1px solid black; font-weight:bold;">Total</th>
        </tr>
      </thead>
      <tbody>

        @foreach($data as $k=>$v)
        <tr>
          <td style="border: 1px solid black;">{{$loop->iteration}}</td>
          <td style="border: 1px solid black;">{{ $v["employee"]["role"] }}</td>
          <td style="border: 1px solid black;">{{ $v["employee"]["name"] }}</td>
          <td style="border: 1px solid black;" class="text-right p-1">{{ $v["employee"]["ktp_no"] }}</td>
          <td style="border: 1px solid black;" class="text-right p-1">{{ $v["employee"]["rek_no"] }}</td>
          <td style="border: 1px solid black;" class="text-right p-1">{{ $v["employee"]["rek_name"] }}</td>
          <td style="border: 1px solid black;" class="text-right p-1">{{ $v["employee"]["bank"] ? $v["employee"]["bank"]["code"] : "" }}</td>
          <td style="border: 1px solid black;" class="text-right p-1">{{ $v["sb_gaji"] }}</td>
          <td style="border: 1px solid black;" class="text-right p-1">{{ $v["sb_makan"] }}</td>
          <td style="border: 1px solid black;" class="text-right p-1">{{ $v["sb_dinas"] }}</td>
          <!-- <td style="border: 1px solid black;" class="text-right p-1">{{ $v["salary_bonus_nominal"] }}</td> -->
          <td style="border: 1px solid black;" class="text-right p-1">{{ $v["total"] }}</td>
        </tr>
        @endforeach
        <tr>
          <td colspan="7" style="text-align: right; border: 1px solid black; font-weight:bold;"> Grand Total </td>
          <td style="border: 1px solid black; font-weight:bold;" class="p-1" > {{$info['ttl_sb_gaji']}} </td>
          <td style="border: 1px solid black; font-weight:bold;" class="p-1" > {{$info['ttl_sb_makan']}} </td>
          <td style="border: 1px solid black; font-weight:bold;" class="p-1" > {{$info['ttl_sb_dinas']}} </td>
          <!-- <td style="border: 1px solid black; font-weight:bold;" class="p-1" > {{$info['ttl_bonus']}} </td> -->
          <td style="border: 1px solid black; font-weight:bold;" class="p-1" > {{$info['ttl_all']}} </td>
        </tr>
      </tbody>
    </table>