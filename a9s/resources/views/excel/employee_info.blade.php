<table class="line borderless text-center mt-2" style="font-size: x-small;">
  <thead class="text-center" style="background-color: #B0A4A4;">
    <tr>
      <th style="border: 1px solid black;">No</th>
      <th style="border: 1px solid black;">ID</th>
      <th style="border: 1px solid black;">Nama</th>
      <th style="border: 1px solid black;">Role</th>
      <th style="border: 1px solid black;">Lokasi</th>
      <th style="border: 1px solid black;">Gaji</th>
      <th style="border: 1px solid black;">Makan</th>
      <th style="border: 1px solid black;">Dinas</th>
      <th style="border: 1px solid black;">Total</th>
    </tr>
  </thead>
  <tbody>  
    @foreach($data as $k=>$v)
      @php
        $rowspan = count($v['location']);    
        $locations = implode(",",array_map(function($x){
          return $x['xto'];
        },$v['location']));


      @endphp
    <tr>
      <td rowspan="{{$rowspan}}" style="border: 1px solid black;">{{$loop->iteration}}</td>
      <td rowspan="{{$rowspan}}" style="border: 1px solid black;">{{ $v["employee"]["id"] }}</td>
      <td rowspan="{{$rowspan}}" style="border: 1px solid black;">{{ $v["employee"]["name"] }}</td>
      <td rowspan="{{$rowspan}}" style="border: 1px solid black;">{{ $v["employee"]["role"] }}</td>
      <td style="border: 1px solid black;">{{$v["location"][0]["xto"]}}</td>
      <td rowspan="{{$rowspan}}" style="border: 1px solid black;">{{ $v["gaji"] }}</td>
      <td rowspan="{{$rowspan}}" style="border: 1px solid black;">{{ $v["makan"] }}</td>
      <td rowspan="{{$rowspan}}" style="border: 1px solid black;">{{ $v["dinas"] }}</td>
      <td rowspan="{{$rowspan}}" style="border: 1px solid black;">{{ $v["total"] }}</td>
    </tr>
    @if($rowspan>1)
    @foreach($v['location'] as $k1=>$v1)
    @if($k1>0)
    <tr>
      <td style="border: 1px solid black;">{{ $v1['xto'] }}</td>
    </tr>
    @endif
    @endforeach
    @endif
    @endforeach
  </tbody>
</table>
