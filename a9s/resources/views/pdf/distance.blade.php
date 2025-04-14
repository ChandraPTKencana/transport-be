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
          <th style="border: 1px solid black;">NoPol</th>
          <th style="border: 1px solid black;">Ttl Jarak</th>
          <th style="border: 1px solid black;">Ttl Biaya Supir</th>
          <th style="border: 1px solid black;">Ttl Biaya Kernet</th>
          <th style="border: 1px solid black;">Ttl Biaya Solar</th>
          <th style="border: 1px solid black;">Ttl Biaya Operasional</th>
          <th style="border: 1px solid black;">Ttl Biaya Lainnya</th>
          <th style="border: 1px solid black;">Ttl Extra Money</th>
          <th style="border: 1px solid black;">Tonase</th> 
          <!-- Lainnya di luar account yang terlist -->
        </tr>
      </thead>
      <tbody>
        @foreach($data as $k=>$v)
        <tr>
          <td>{{$loop->iteration}}</td>
          <td>{{ $v["no_pol"] }}</td>
          <td class="text-right p-1">{{ $v["distance"] }}</td>
          <td class="text-right p-1">{{ $v["supir"] }}</td>
          <td class="text-right p-1">{{ $v["kernet"] }}</td>
          <td class="text-right p-1">{{ $v["solar"] }}</td>
          <td class="text-right p-1">{{ $v["operasional"] }}</td>
          <td class="text-right p-1">{{ $v["lainnya"] }}</td>
          <td class="text-right p-1">{{ $v["extra_money"] }}</td>
          <td class="text-right p-1">{{ $v["tonase"] }}</td>
        </tr>
        @endforeach
        <tr>
          <td colspan="2" style="border:none;"></td>
          <td class="text-right p-1">{{ $info["ttl_distance"] }}</td>
          <td class="text-right p-1">{{ $info["ttl_supir"] }}</td>
          <td class="text-right p-1">{{ $info["ttl_kernet"] }}</td>
          <td class="text-right p-1">{{ $info["ttl_solar"] }}</td>
          <td class="text-right p-1">{{ $info["ttl_operasional"] }}</td>
          <td class="text-right p-1">{{ $info["ttl_lainnya"] }}</td>
          <td class="text-right p-1">{{ $info["ttl_extra_money"] }}</td>
          <td class="text-right p-1">{{ $info["ttl_tonase"] }}</td>
        </tr>
      </tbody>
    </table>
    </div>
  </main>

</body>

</html>