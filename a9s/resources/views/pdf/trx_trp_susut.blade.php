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
        <tr>
          <th colspan="2" style="border:none;" class="text-right"> Note : Angka Merah apabila >= 0.4 atau &lt;= -0.4</th>
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

            <th rowspan="2" style="border: 1px solid black;">Berangkat</th>
            <th rowspan="2" style="border: 1px solid black;">Kembali</th>
            <th colspan="4" style="border: 1px solid black;">Bruto</th>
            <th colspan="4" style="border: 1px solid black;">Tara</th>
            <th colspan="4" style="border: 1px solid black;">Netto</th>
            <th colspan="1" style="border: 1px solid black;">Biaya</th>
          </tr>
          <tr>
            <th>Kirim</th>
            <th>Terima</th>
            <th>Selisih</th>
            <th>% Selisih</th>
            <th>Kirim</th>
            <th>Terima</th>
            <th>Selisih</th>
            <th>% Selisih</th>
            <th>Kirim</th>
            <th>Terima</th>
            <th>Selisih</th>
            <th>% Selisih</th>
            <th>Ujalan</th>
          </tr>
        </thead>
        <tbody>
          
          @foreach($data as $k=>$v)
          <tr>
            <td>{{$loop->iteration}}</td>
            <td>{{ $v["id"] }}</td>
            <td>{{ $v["tanggal"] }}</td>
            <td>{{ $v["no_pol"] }}</td>
            <td>{{ $v["jenis"] }}</td>
            <td>{{ $v["xto"] }}</td>

            <td>{{ $v["ticket_a_out_at"] }}</td>
            <td>{{ $v["ticket_b_in_at"] }}</td>
            <td>{{ $v["ticket_a_bruto"] }}</td>
            <td>{{ $v["ticket_b_bruto"] }}</td>
            <td>{{ $v["ticket_b_a_bruto"] }}</td>
            <td style="<?= $v['ticket_b_a_bruto_persen_red']; ?>">{{ $v["ticket_b_a_bruto_persen"] }}</td>
            <td>{{ $v["ticket_a_tara"] }}</td>
            <td>{{ $v["ticket_b_tara"] }}</td>
            <td>{{ $v["ticket_b_a_tara"] }}</td>
            <td style="<?= $v['ticket_b_a_tara_persen_red']; ?>">{{ $v["ticket_b_a_tara_persen"] }}</td>
            <td>{{ $v["ticket_a_netto"] }}</td>
            <td>{{ $v["ticket_b_netto"] }}</td>
            <td>{{ $v["ticket_b_a_netto"] }}</td>
            <td style="<?= $v['ticket_b_a_netto_persen_red']; ?>">{{ $v["ticket_b_a_netto_persen"] }}</td>
            <td>{{ $v["amount"] }}</td>
          </tr>
          @endforeach
          <tr>
            <td colspan="8" style="border:none;"></td>
            <td>{{ $info["ttl_a_bruto"] }}</td>
            <td>{{ $info["ttl_b_bruto"] }}</td>
            <td>{{ $info["ttl_b_a_bruto"] }}</td>
            <td style="border:none;"></td>
            <td>{{ $info["ttl_a_tara"] }}</td>
            <td>{{ $info["ttl_b_tara"] }}</td>
            <td>{{ $info["ttl_b_a_tara"] }}</td>
            <td style="border:none;"></td>
            <td>{{ $info["ttl_a_netto"] }}</td>
            <td>{{ $info["ttl_b_netto"] }}</td>
            <td>{{ $info["ttl_b_a_netto"] }}</td>
            <td colspan="3" style="border: none;"></td>
          </tr>
        </tbody>
      </table>
    </div>
  </main>
</body>
</html>