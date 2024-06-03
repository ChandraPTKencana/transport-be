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
        @if(in_array('ticket_a_netto',$shows))
        <tr>
          <th colspan="2" style="border:none;" class="text-right"> Note : Angka Merah apabila sama dengan atau lebih maupun kurang dari 0.4</th>
        </tr>
        @endif
      </thead>
    </table>
    <table class="line borderless text-center mt-2" style="font-size: x-small;">
        <thead class="text-center" style="background-color: #B0A4A4;">
          <tr>
            <th rowspan="2" style="border: 1px solid black;">No</th>
            @if(in_array('id',$shows))
            <th rowspan="2" style="border: 1px solid black;">ID</th>
            @endif
            @if(in_array('tanggal',$shows))
            <th rowspan="2" style="border: 1px solid black;">Tanggal</th>
            @endif
            @if(in_array('no_pol',$shows))
            <th rowspan="2" style="border: 1px solid black;">No Pol</th>
            @endif
            @if(in_array('jenis',$shows))
            <th rowspan="2" style="border: 1px solid black;">Spec</th>
            @endif
            @if(in_array('xto',$shows))
            <th rowspan="2" style="border: 1px solid black;">Tujuan</th>
            @endif
            @if(in_array('ticket_a_out_at',$shows))
            <th rowspan="2" style="border: 1px solid black;">Berangkat</th>
            @endif
            @if(in_array('ticket_b_in_at',$shows))
            <th rowspan="2" style="border: 1px solid black;">Kembali</th>
            @endif
            @if(in_array('ticket_a_bruto',$shows) || in_array('ticket_b_bruto',$shows) || in_array('ticket_b_a_bruto',$shows) || in_array('ticket_b_a_bruto_persen',$shows) )
            <th colspan="4" style="border: 1px solid black;">Bruto</th>
            @endif
            @if(in_array('ticket_a_tara',$shows) || in_array('ticket_b_tara',$shows) || in_array('ticket_b_a_tara',$shows) || in_array('ticket_b_a_tara_persen',$shows) )
            <th colspan="4" style="border: 1px solid black;">Tara</th>
            @endif
            @if(in_array('ticket_a_netto',$shows) || in_array('ticket_b_netto',$shows) || in_array('ticket_b_a_netto',$shows) || in_array('ticket_b_a_netto_persen',$shows) )
            <th colspan="4" style="border: 1px solid black;">Netto</th>
            @endif
            @if(in_array('amount',$shows) || in_array('pv_total',$shows) || in_array('pv_no',$shows || in_array('pvr_no',$shows) || in_array('pv_datetime',$shows)))
              @php
                $x=0;
                if(in_array('amount',$shows))
                $x++;
                if(in_array('pv_total',$shows))
                $x++;
                if(in_array('pv_no',$shows))
                $x++;
                if(in_array('pvr_no',$shows))
                $x++;
                if(in_array('pv_datetime',$shows))
                $x++;
            @endphp
              <th colspan="{{$x}}" style="border: 1px solid black;">Biaya</th>
            @endif
          </tr>
          <tr>
            @if(in_array('ticket_a_bruto',$shows))  
            <th>Kirim</th>
            @endif
            @if(in_array('ticket_b_bruto',$shows))
            <th>Terima</th>
            @endif
            @if(in_array('ticket_b_a_bruto',$shows))
            <th>Selisih</th>
            @endif
            @if(in_array('ticket_b_a_bruto_persen',$shows))
            <th>% Selisih</th>
            @endif
            @if(in_array('ticket_a_tara',$shows))
            <th>Kirim</th>
            @endif
            @if(in_array('ticket_b_bruto',$shows))
            <th>Terima</th>
            @endif
            @if(in_array('ticket_b_a_bruto',$shows))
            <th>Selisih</th>
            @endif
            @if(in_array('ticket_b_a_bruto_persen',$shows))
            <th>% Selisih</th>
            @endif
            @if(in_array('ticket_a_netto',$shows))
            <th>Kirim</th>
            @endif
            @if(in_array('ticket_b_bruto',$shows))
            <th>Terima</th>
            @endif
            @if(in_array('ticket_b_a_bruto',$shows))
            <th>Selisih</th>
            @endif
            @if(in_array('ticket_b_a_bruto_persen',$shows))
            <th>% Selisih</th>
            @endif
            @if(in_array('amount',$shows))
            <th>Ujalan</th>
            @endif
            @if(in_array('pv_total',$shows))
            <th>PV Total</th>
            @endif
            @if(in_array('pv_datetime',$shows))
            <th>PV Date</th>
            @endif
            @if(in_array('pv_no',$shows))
            <th>PV No</th>
            @endif
            @if(in_array('pvr_no',$shows))
            <th>PVR No</th>
            @endif
          </tr>
        </thead>
        <tbody>
          
          @foreach($data as $k=>$v)
          <tr>
            <td>{{$loop->iteration}}</td>
            @if(in_array('id',$shows))
            <td>{{ $v["id"] }}</td>
            @endif
            @if(in_array('tanggal',$shows))
            <td>{{ $v["tanggal"] }}</td>
            @endif
            @if(in_array('no_pol',$shows))
            <td>{{ $v["no_pol"] }}</td>
            @endif
            @if(in_array('jenis',$shows))
            <td>{{ $v["jenis"] }}</td>
            @endif
            @if(in_array('xto',$shows))
            <td>{{ $v["xto"] }}</td>
            @endif
            @if(in_array('ticket_a_out_at',$shows))
            <td>{{ $v["ticket_a_out_at"] }}</td>
            @endif
            @if(in_array('ticket_b_in_at',$shows))
            <td>{{ $v["ticket_b_in_at"] }}</td>
            @endif
            @if(in_array('ticket_a_bruto',$shows))
            <td>{{ $v["ticket_a_bruto"] }}</td>
            @endif
            @if(in_array('ticket_b_bruto',$shows))
            <td>{{ $v["ticket_b_bruto"] }}</td>
            @endif
            @if(in_array('ticket_b_a_bruto',$shows))
            <td>{{ $v["ticket_b_a_bruto"] }}</td>
            @endif
            @if(in_array('ticket_b_a_bruto_persen',$shows))
            <td style="<?= $v['ticket_b_a_bruto_persen_red']; ?>">{{ $v["ticket_b_a_bruto_persen"] }}</td>
            @endif
            @if(in_array('ticket_a_tara',$shows))
            <td>{{ $v["ticket_a_tara"] }}</td>
            @endif
            @if(in_array('ticket_b_tara',$shows))
            <td>{{ $v["ticket_b_tara"] }}</td>
            @endif
            @if(in_array('ticket_b_a_tara',$shows))
            <td>{{ $v["ticket_b_a_tara"] }}</td>
            @endif
            @if(in_array('ticket_b_a_tara_persen',$shows))
            <td style="<?= $v['ticket_b_a_tara_persen_red']; ?>">{{ $v["ticket_b_a_tara_persen"] }}</td>
            @endif
            @if(in_array('ticket_a_netto',$shows))
            <td>{{ $v["ticket_a_netto"] }}</td>
            @endif
            @if(in_array('ticket_b_netto',$shows))
            <td>{{ $v["ticket_b_netto"] }}</td>
            @endif
            @if(in_array('ticket_b_a_netto',$shows))
            <td>{{ $v["ticket_b_a_netto"] }}</td>
            @endif
            @if(in_array('ticket_b_a_netto_persen',$shows))
            <td style="<?= $v['ticket_b_a_netto_persen_red']; ?>">{{ $v["ticket_b_a_netto_persen"] }}</td>
            @endif
            @if(in_array('amount',$shows))
            <td>{{ $v["amount"] }}</td>
            @endif
            @if(in_array('pv_total',$shows))
            <td>{{ $v["pv_total"] }}</td>
            @endif
            @if(in_array('pv_datetime',$shows))
            <td>{{ $v["pv_datetime"] }}</td>
            @endif
            @if(in_array('pv_no',$shows))
            <td>{{ $v["pv_no"] }}</td>
            @endif
            @if(in_array('pvr_no',$shows))
            <td>{{ $v["pvr_no"] }}</td>
            @endif
          </tr>
          @endforeach
          @if(in_array('ticket_a_netto',$shows))
          <tr>
            <td colspan="7" style="border:none;"></td>
            @if(in_array('ticket_a_bruto',$shows))
            <td>{{ $info["ttl_a_bruto"] }}</td>
            @endif
            @if(in_array('ticket_b_bruto',$shows))
            <td>{{ $info["ttl_b_bruto"] }}</td>
            @endif
            @if(in_array('ticket_b_a_bruto',$shows))
            <td>{{ $info["ttl_b_a_bruto"] }}</td>
            @endif
            @if(in_array('ticket_b_a_bruto_persen',$shows))
            <td style="border:none;"></td>

            @endif
            @if(in_array('ticket_a_tara',$shows))
            <td>{{ $info["ttl_a_tara"] }}</td>
            @endif
            @if(in_array('ticket_b_tara',$shows))
            <td>{{ $info["ttl_b_tara"] }}</td>
            @endif
            @if(in_array('ticket_b_a_tara',$shows))
            <td>{{ $info["ttl_b_a_tara"] }}</td>
            @endif
            @if(in_array('ticket_b_a_tara_persen',$shows))
            <td style="border:none;"></td>
            @endif
            @if(in_array('ticket_a_netto',$shows))
            <td>{{ $info["ttl_a_netto"] }}</td>
            @endif
            @if(in_array('ticket_b_netto',$shows))
            <td>{{ $info["ttl_b_netto"] }}</td>
            @endif
            @if(in_array('ticket_b_a_netto',$shows))
            <td>{{ $info["ttl_b_a_netto"] }}</td>
            @endif
            @if(in_array('ticket_b_a_netto_persen',$shows))
            <td colspan="3" style="border: none;"></td>
            @endif
          </tr>
          @endif
        </tbody>
      </table>
    
    </div>
  </main>

</body>

</html>