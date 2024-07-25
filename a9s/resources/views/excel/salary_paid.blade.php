<table class="line borderless text-center mt-2" style="font-size: x-small;">
      <thead class="text-center" style="background-color: #B0A4A4;">
        <tr>
          <th style="border: 1px solid black;">No</th>
          <th style="border: 1px solid black;">Nama Pekerja</th>
          <th style="border: 1px solid black;">No KTP</th>
          <th style="border: 1px solid black;">No SIM</th>
          <th style="border: 1px solid black;">Rek No</th>
          <th style="border: 1px solid black;">Rek Name</th>
          <th style="border: 1px solid black;">Bank Name</th>
          <th style="border: 1px solid black;">Nominal Standby</th>
          <th style="border: 1px solid black;">Nominal Bonus</th>
          <th style="border: 1px solid black;">Total</th>
        </tr>
      </thead>
      <tbody>
        
        @foreach($data as $k=>$v)
        <tr>
          <td style="border: 1px solid black;">{{$loop->iteration}}</td>
          <td style="border: 1px solid black;">{{ $v["employee"]["name"] }}</td>
          <td style="border: 1px solid black;" class="text-right p-1">{{ $v["employee"]["ktp_no"] }}</td>
          <td style="border: 1px solid black;" class="text-right p-1">{{ $v["employee"]["sim_no"] }}</td>
          <td style="border: 1px solid black;" class="text-right p-1">{{ $v["employee"]["rek_no"] }}</td>
          <td style="border: 1px solid black;" class="text-right p-1">{{ $v["employee"]["rek_name"] }}</td>
          <td style="border: 1px solid black;" class="text-right p-1">{{ $v["employee"]["bank_name"] }}</td>
          <td style="border: 1px solid black;" class="text-right p-1">{{ $v["standby_nominal"] }}</td>
          <td style="border: 1px solid black;" class="text-right p-1">{{ $v["salary_bonus_nominal"] }}</td>
          <td style="border: 1px solid black;" class="text-right p-1">{{ $v["total"] }}</td>
        </tr>
        @endforeach
        <tr>
          <td colspan="7" style="text-align: right; border: 1px solid black;"> Grand Total</td>
          <td style="border: 1px solid black;" class="p-1" > {{$info['ttl_standby']}}</td>
          <td style="border: 1px solid black;" class="p-1" > {{$info['ttl_bonus']}}</td>
          <td style="border: 1px solid black;" class="p-1" > {{$info['ttl_all']}}</td>
        </tr>
      </tbody>
    </table>