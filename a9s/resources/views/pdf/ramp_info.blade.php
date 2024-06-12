<html>

<head>

  <!-- <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.3.1/dist/css/bootstrap.min.css" integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T" crossorigin="anonymous"> -->
  <!-- <link rel="stylesheet" href="{{asset('css/bootstrap.min.css')}}"> -->
  <!-- <link href="http://fonts.googleapis.com/css2?family=Noto+Sans&display=swap" rel="stylesheet">
  <link href="http://fonts.googleapis.com/css2?family=Amiri&display=swap" rel="stylesheet"> -->

  <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
  <!-- <link rel="stylesheet" href="bootstrap.min.css">
  <link rel="stylesheet" href="mycss.css"> -->
  <link rel="stylesheet" href="{{asset('bootstrap.min.css')}}">
  <link rel="stylesheet" href="{{asset('mycss.css')}}">

  <style>
    /* @page {
      margin: 90px 15px 22px 15px;
    } */
    @page {
      margin: 10px 15px 22px 15px;
    }

    .line table,
    th,
    td {
      border: 1px solid black;
      border-collapse: collapse;
      /* border-collapse: separate; */
    }

    th,td{
      padding:2px;
    }

    .head td {
      padding: 0;
    }

    table {
      /* page-break-inside: avoid !important; */
    }

    table tr:nth-child(even) td{
      background-color: #dddddd;
    }

    .pagenum:before {
      content: counter(page);
    }
  </style>
</head>

<body>
  <main>
    <div>
    <table style="width: 100%; table-layout:fixed;">
      <thead >
        <tr >
          <th style="border:none;"> Laporan dari tanggal {{$info["from"]}} s/d  {{$info["to"]}} </th>
          <th style="border:none;" class="text-right"> Tanggal Cetak {{$info["now"]}} </th>
        </tr>
      </thead>
    </table>
    <table class="line borderless text-center mt-2" style="font-size: x-small;">
      <thead class="text-center" style="background-color: #B0A4A4;">
        <tr>
          <th style="border: 1px solid black;">No</th>
          <th style="border: 1px solid black;">Ramp</th>
          <th style="border: 1px solid black;">Supir</th>
          <th style="border: 1px solid black;">Kernet</th>
          <th style="border: 1px solid black;">Gaji Supir</th>
          <th style="border: 1px solid black;">Gaji Kernet</th>
          <th style="border: 1px solid black;">U.Makan Supir</th>
          <th style="border: 1px solid black;">U.Makan Kernet</th>
          <th style="border: 1px solid black;">Tonase</th>
          <th style="border: 1px solid black;">Rata2 Tonase</th>
          <th style="border: 1px solid black;">Trip</th>
        </tr>
      </thead>
      <tbody>
        
        @foreach($data as $k=>$v)
        <tr>
          <td>{{$loop->iteration}}</td>
          <td>{{ $v["xto"] }}</td>
          <td class="text-right p-1">{{ $v["z_supir"] }}</td>
          <td class="text-right p-1">{{ $v["z_kernet"] }}</td>
          <td class="text-right p-1">{{ $v["z_gaji_supir"] }}</td>
          <td class="text-right p-1">{{ $v["z_gaji_kernet"] }}</td>
          <td class="text-right p-1">{{ $v["z_makan_supir"] }}</td>
          <td class="text-right p-1">{{ $v["z_makan_kernet"] }}</td>
          <td class="text-right p-1">{{ $v["tonase"] }}</td>
          <td class="text-right p-1">{{ $v["z_rt_tonase"] }}</td>
          <td class="text-right p-1">{{ $v["trip"] }}</td>
        </tr>
        @endforeach
        <tr>
          <td colspan="2" style="border:none;"></td>
          <td class="text-right p-1">{{ $info["ttl_supir"] }}</td>
          <td class="text-right p-1">{{ $info["ttl_kernet"] }}</td>
          <td class="text-right p-1">{{ $info["ttl_gaji_supir"] }}</td>
          <td class="text-right p-1">{{ $info["ttl_gaji_kernet"] }}</td>
          <td class="text-right p-1">{{ $info["ttl_makan_supir"] }}</td>
          <td class="text-right p-1">{{ $info["ttl_makan_kernet"] }}</td>
          <td class="text-right p-1">{{ $info["ttl_tonase"] }} </td>
          <td class="text-right p-1">{{ $info["ttl_rt_tonase"] }}</td>
          <td class="text-right p-1">{{ $info["ttl_trip"] }}</td>
        </tr>
      </tbody>
    </table>
    </div>
  </main>

</body>

</html>