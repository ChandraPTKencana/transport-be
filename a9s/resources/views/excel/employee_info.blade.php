<table class="line borderless text-center mt-2" style="font-size: x-small;">
  <thead class="text-center" style="background-color: #B0A4A4;">
    <tr>
      <th rowspan="2" style="border: 1px solid black;">No</th>
      <th rowspan="2" style="border: 1px solid black;">ID</th>
      <th rowspan="2" style="border: 1px solid black;">Nama</th>
      <th rowspan="2" style="border: 1px solid black;">Role</th>
      <th rowspan="2" style="border: 1px solid black;">Jlh Trip</th>
      <th colspan="6" style="border: 1px solid black;">Lokasi</th>
      <th rowspan="2" style="border: 1px solid black;">Gaji</th>
      <th rowspan="2" style="border: 1px solid black;">Makan</th>
      <th rowspan="2" style="border: 1px solid black;">Dinas</th>
      <th rowspan="2" style="border: 1px solid black;">Total</th>
    </tr>
    <tr>
      <th style="border: 1px solid black;">ID</th>
      <th style="border: 1px solid black;">To</th>
      <th style="border: 1px solid black;">Gaji</th>
      <th style="border: 1px solid black;">Makan</th>
      <th style="border: 1px solid black;">Dinas</th>
      <th style="border: 1px solid black;">Jlh Trip</th>
    </tr>
  </thead>
  <tbody>  
    @foreach($data as $k=>$v)
      @php
        $rowspan = count($v['location']);    
        $locations = implode(",",array_map(function($x){
          return $x["uj"]['xto'];
        },$v['location']));


      @endphp
    <tr>
      <td rowspan="{{$rowspan}}" style="border: 1px solid black;">{{$loop->iteration}}</td>
      <td rowspan="{{$rowspan}}" style="border: 1px solid black;">{{ $v["employee"]["id"] }}</td>
      <td rowspan="{{$rowspan}}" style="border: 1px solid black;">{{ $v["employee"]["name"] }}</td>
      <td rowspan="{{$rowspan}}" style="border: 1px solid black;">{{ $v["employee"]["role"] }}</td>
      <td rowspan="{{$rowspan}}" style="border: 1px solid black;">{{ $v["jlh_trip"] }}</td>
      <td style="border: 1px solid black;">{{$v["location"][0]["uj"]["id"]}}</td>
      <td style="border: 1px solid black;">{{$v["location"][0]["uj"]["xto"]}}</td>
      <td style="border: 1px solid black;">{{$v["location"][0]["gaji"]}}</td>
      <td style="border: 1px solid black;">{{$v["location"][0]["makan"]}}</td>
      <td style="border: 1px solid black;">{{$v["location"][0]["dinas"]}}</td>
      <td style="border: 1px solid black;">{{$v["location"][0]["jlh_trip"]}}</td>
      <td rowspan="{{$rowspan}}" style="border: 1px solid black;">{{ $v["gaji"] }}</td>
      <td rowspan="{{$rowspan}}" style="border: 1px solid black;">{{ $v["makan"] }}</td>
      <td rowspan="{{$rowspan}}" style="border: 1px solid black;">{{ $v["dinas"] }}</td>
      <td rowspan="{{$rowspan}}" style="border: 1px solid black;">{{ $v["total"] }}</td>
    </tr>
    @if($rowspan>1)
    @foreach($v['location'] as $k1=>$v1)
    @if($k1>0)
    <tr>
      <td style="border: 1px solid black;">{{$v1["uj"]['id'] }}</td>
      <td style="border: 1px solid black;">{{$v1["uj"]["xto"]}}</td>
      <td style="border: 1px solid black;">{{$v1["gaji"]}}</td>
      <td style="border: 1px solid black;">{{$v1["makan"]}}</td>
      <td style="border: 1px solid black;">{{$v1["dinas"]}}</td>
      <td style="border: 1px solid black;">{{$v1["jlh_trip"]}}</td>
    </tr>
    @endif
    @endforeach
    @endif
    @endforeach
  </tbody>
</table>
