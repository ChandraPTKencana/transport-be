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
          <th style="border:none;"> Laporan Periode {{$info["periode"]}} </th>
          <th style="border:none;" class="text-right"> Tanggal Cetak {{$info["now"]}} </th>
        </tr>
      </thead>
    </table>
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
          <td>{{$loop->iteration}}</td>
          <td>{{ $v["employee"]["name"] }}</td>
          <td class="text-right p-1">{{ $v["employee"]["ktp_no"] }}</td>
          <td class="text-right p-1">{{ $v["employee"]["sim_no"] }}</td>
          <td class="text-right p-1">{{ $v["employee"]["rek_no"] }}</td>
          <td class="text-right p-1">{{ $v["employee"]["rek_name"] }}</td>
          <td class="text-right p-1">{{ $v["employee"]["bank"] ? $v["employee"]["bank"]["code"] : "" }}</td>
          <td class="text-right p-1">{{ $v["standby_nominal"] }}</td>
          <td class="text-right p-1">{{ $v["salary_bonus_nominal"] }}</td>
          <td class="text-right p-1">{{ $v["total"] }}</td>
        </tr>
        @endforeach
        <tr>
          <td colspan="7" style="text-align: right;"> Grand Total</td>
          <td class="p-1" > {{$info['ttl_standby']}}</td>
          <td class="p-1" > {{$info['ttl_bonus']}}</td>
          <td class="p-1" > {{$info['ttl_all']}}</td>
        </tr>
      </tbody>
    </table>
    </div>
  </main>

</body>

</html>