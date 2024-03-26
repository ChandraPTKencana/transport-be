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
      page-break-inside: avoid !important;
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

    <table class="line borderless text-center mt-2" style="font-size: x-small;">
        <thead class="text-center" style="background-color: #B0A4A4;">
      
          <tr>
            <th rowspan="2" style="border: 1px solid black;">No</th>
            <th rowspan="2" style="border: 1px solid black;">Tanggal</th>
            <th rowspan="2" style="border: 1px solid black;">No Pol</th>
            <th rowspan="2" style="border: 1px solid black;">Spec</th>
            <th rowspan="2" style="border: 1px solid black;">Tujuan</th>
            <th rowspan="2" style="border: 1px solid black;">Berangkat</th>
            <th rowspan="2" style="border: 1px solid black;">Kembali</th>
            <th colspan="3" style="border: 1px solid black;">Bruto</th>
            <th colspan="3" style="border: 1px solid black;">Tara</th>
            <th colspan="3" style="border: 1px solid black;">Netto</th>

            <th rowspan="2" style="border: 1px solid black;">% Susut</th>
            <th colspan="2" style="border: 1px solid black;">Biaya</th>
          </tr>
          <tr>
            <th>Kirim</th>
            <th>Terima</th>
            <th>Selisih</th>
            <th>Kirim</th>
            <th>Terima</th>
            <th>Selisih</th>
            <th>Kirim</th>
            <th>Terima</th>
            <th>Selisih</th>
            <th>Ujalan</th>
            <th>PV</th>
          </tr>
        </thead>
        <tbody>
          
          @foreach($data as $k=>$v)
          <tr>
            <!-- <td>{{json_encode($v)}}</td> -->
            <td>{{$loop->iteration}}</td>
            <td>{{ date("d-m-Y",strtotime($v["tanggal"])) }}</td>
            <td>{{ $v["no_pol"] }}</td>
            <td>{{ $v["jenis"] }}</td>
            <td>{{ $v["xto"] }}</td>
            <td>{{ date("d-m-Y H:i",strtotime($v["ticket_a_out_at"])) }}</td>
            <td>{{ date("d-m-Y H:i",strtotime($v["ticket_b_in_at"])) }}</td>
            <td>{{ number_format($v["ticket_a_bruto"], 0,',','.') }}</td>
            <td>{{ number_format($v["ticket_b_bruto"], 0,',','.') }}</td>
            <td>{{ block_negative($v["ticket_a_bruto"] - $v["ticket_b_bruto"])  }}</td>
            <td>{{ number_format($v["ticket_a_tara"], 0,',','.') }}</td>
            <td>{{ number_format($v["ticket_b_tara"], 0,',','.') }}</td>
            <td>{{ block_negative($v["ticket_a_tara"] - $v["ticket_b_tara"]) }}</td>
            <td>{{ number_format($v["ticket_a_netto"], 0,',','.') }}</td>
            <td>{{ number_format($v["ticket_b_netto"], 0,',','.') }}</td>
            <td>{{ block_negative($v["ticket_a_netto"] - $v["ticket_b_netto"]) }}</td>
            <td>{{ number_format(($v["ticket_a_netto"] - $v["ticket_b_netto"])/$v["ticket_a_bruto"] * 100, 2,',','.') }}</td>
            <td>{{ number_format($v["amount"], 0,',','.') }}</td>
            <td>{{ number_format($v["pv_total"], 0,',','.') }}</td>
          </tr>
          
          @endforeach
          
        </tbody>
      </table>
    
    </div>
  </main>

</body>

</html>