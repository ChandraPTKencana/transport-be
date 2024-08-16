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


  <!-- Define header and footer blocks before your content -->
  <!-- <header class="fixed-top" style="top: -70px;">
    <table class="table table-sm text-center" style="border: 1px solid black;">
        <tr>
            <td class="align-middle" style="border:none;">
                <img src="artifindo.jpg" width="100px" height="60px" />
            </td>
            <td class="align-middle" style="border:none;">
                <div class="mavenpro-semi-bold" style="font-size:25px; height:25px;"> PT ARMADA KREATIF INDOPASIFIK </div>
                <div style="font-size:12px;"> Gudang Multi Fungsi No.02 Jl.Pulau Nias Selatan 1, KIM II, Kec Percut Sei Tuan, Kab Deli Serdang Sumatera Utara - 20242, No.Telp: 0811 6537 611 </div>
            </td>
            <td class="align-middle" style="border: 1px solid black;">
                
                <span class="pagenum"></span>
            </td>
        </tr>
    </table>
  </header> -->
  <!-- <footer class="fixed-bottom" style="top: 100%; font-size: x-small;">

  </footer> -->

  <!-- Wrap the content of your PDF inside a main tag -->
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
          <th rowspan="2" style="border: 1px solid black;">No</th>
            <th rowspan="2" style="border: 1px solid black;">ID</th>
            <th rowspan="2" style="border: 1px solid black;">Tanggal</th>
            <th rowspan="2" style="border: 1px solid black;">No Pol</th>
            <th rowspan="2" style="border: 1px solid black;">Jenis</th>
            <th rowspan="2" style="border: 1px solid black;">Tujuan</th>

            <th colspan="5" style="border: 1px solid black;">Biaya</th>
          </tr>
          <tr>
            <th>Ujalan</th>
            <th>PV Total</th>
            <th>PV Date</th>
            <th>PV No</th>
            <th>PVR No</th>
          </tr>
        </thead>
        <tbody>
          @foreach($data as $k=>$v)
          <tr>
            <td>{{ $loop->iteration }}</td>
            <td>{{ $v["id"] }}</td>
            <td>{{ $v["tanggal"] }}</td>
            <td>{{ $v["no_pol"] }}</td>
            <td>{{ $v["jenis"] }}</td>
            <td>{{ $v["xto"] }}</td>

            <td>{{ $v["amount"] }}</td>
            <td>{{ $v["pv_total"] }}</td>
            <td>{{ $v["pv_datetime"] }}</td>
            <td>{{ $v["pv_no"] }}</td>
            <td>{{ $v["pvr_no"] }}</td>
          </tr>
          @endforeach
        </tbody>
      </table>
    
    </div>
  </main>

</body>

</html>