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
      margin: 0px 0px 0px 0px;
    }

    .line table,
    th,
    td {
      /* border: 1px solid black; */
      /* border-collapse: collapse; */
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
    <div style="width:238px; padding:0px 27px; font-family:sans-serif">
      <div style="width:100%; border:solid 1px #000; font-size:13px;">

        <div class="text-center">
          Perincian U.jalan {{$jenis}} <br>
          {{$asal}} - {{$xto}} 
        </div>
        <table style="font-size: 11px; ">
          <tr>
            <td> No.Polisi </td>
            <td> : </td>
            <td> {{$no_pol}} </td>
          </tr>
          <tr>
            <td> Nama Supir </td>
            <td> : </td>
            <td> {{$supir}} </td>
          </tr>
        </table>

        <table class="line borderless text-center mt-2" style="font-size: 11px; width:100%;">
          
          <tbody>
            @foreach($details as $k=>$v)
            
            <tr>
              <td style="text-align: left;">{{ $v["xdesc"] }} 
                @if($v["qty"]>1) 
                (                  
                  {{ number_format($v["qty"], 0,',','.') }}
                  x
                  Rp. {{ number_format($v["harga"], 0,',','.') }}
                )
                @endif
              </td>
              <td>:</td>
              <td>Rp. {{ number_format(($v["qty"] * $v["harga"]), 0,',','.') }}</td>
            </tr>
            @endforeach
            
          </tbody>
        </table>
      
      </div>
    </div>
  </main>

</body>

</html>