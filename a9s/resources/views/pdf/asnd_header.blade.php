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
        <th style="border: 1px solid black;">Nama</th>
        <th style="border: 1px solid black;">Jabatan</th>
        <th style="border: 1px solid black;">UJ.Gaji</th>
        <th style="border: 1px solid black;">UJ.Makan</th>
        <th style="border: 1px solid black;">SB.Gaji</th>
        <th style="border: 1px solid black;">SB.Makan</th>
        <th style="border: 1px solid black;">Total</th>
      </tr>
      </thead>
      <tbody>
        
        @foreach($data as $k=>$v)
        <tr>
          <td>{{ $v["nama"] }}</td>
          <td class="text-right p-1">{{ $v["jabatan"] }}</td>
          <td class="text-right p-1">{{ $v["uj_gaji"] }}</td>
          <td class="text-right p-1">{{ $v["uj_makan"] }}</td>
          <td class="text-right p-1">{{ $v["sb_gaji"] }}</td>
          <td class="text-right p-1">{{ $v["sb_makan"] }}</td>
          <td class="text-right p-1">{{ $v["total"] }}</td>
        </tr>
        @endforeach
        <tr>
          <td class="p-1" colspan="2" style="text-align: right;"> Grand Total</td>
          <td class="p-1" > {{$info['uj_gaji']}}</td>
          <td class="p-1" > {{$info['uj_makan']}}</td>
          <td class="p-1" > {{$info['sb_gaji']}}</td>
          <td class="p-1" > {{$info['sb_gaji']}}</td>
          <td class="p-1" > {{$info['total']}}</td>
        </tr>
      </tbody>
    </table>
    </div>
  </main>

</body>

</html>