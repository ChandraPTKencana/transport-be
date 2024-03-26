<html>

<head>

  <!-- <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.3.1/dist/css/bootstrap.min.css" integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T" crossorigin="anonymous"> -->
  <!-- <link rel="stylesheet" href="{{asset('css/bootstrap.min.css')}}"> -->
  <!-- <link href="http://fonts.googleapis.com/css2?family=Noto+Sans&display=swap" rel="stylesheet">
  <link href="http://fonts.googleapis.com/css2?family=Amiri&display=swap" rel="stylesheet"> -->

  <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
  <link rel="stylesheet" href="bootstrap.min.css">
  <link rel="stylesheet" href="mycss.css">

  <style>
    @page {
      margin: 90px 15px 22px 15px;
    }

    .line table,
    th,
    td {
      border: 1px solid black;
      border-collapse: collapse;
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
                {{$title}}
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

      <div class="mt-3" style="clear:both;">
        <table class="table-borderless" style="float: left; font-size: x-small;">
          <tr>
            <td style="font-weight: bold;" colspan=" 2">KEPADA YTH:</td>
          </tr>
          <tr>
            <td colspan="2">{{$supplier['name']}}</td>
          </tr>
          <tr>
            <td colspan="2">{{$supplier['address']}}</td>
          </tr>
          <tr>
            <td>Telp. {{$supplier['phone_number']}}</td>
            <td>Hp. {{$supplier['hp_number']}}</td>
          </tr>
        </table>
        <table style="float: right; font-size: x-small;">
          <tr>
            <td>NOMOR</td>
            <td> : {{$no}}</td>
          </tr>
          <tr>
            <td>TANGGAL</td>
            <td> : {{$published_at_id}}</td>
          </tr>
          <tr>
            <td>FRANCO</td>
            <td> : {{$franco}}</td>
          </tr>
          <tr>
            <td>TRANSIT</td>
            <td> : {{$transit}}</td>
          </tr>
          <tr>
            <td>PENYERAHAN</td>
            <td> : {{$transfer_days}} hari</td>
          </tr>
          <tr>
            <td>PALING LAMBAT</td>
            <td> : {{$at_the_latest_id}}</td>
          </tr>
        </table>

      </div>

      <div style="clear:both; font-size: x-small;">

        @if($pp['need'])
        <span style="font-weight: bold;"> Keperluan : </span> {{$pp['need']}}
        @else
        <span style="font-weight: bold;"> NO PROJECT : </span>{{ $pp['project_no']}}
        @endif
      </div>

      <div style="clear:both;">
        <table class="line table table-sm text-center mt-2" style="font-size: x-small;">
          <thead class="text-center" style="background-color: #B0A4A4;">
            <tr>
              <th style="border: 1px solid black;">No</th>
              <th style="border: 1px solid black;">Kode Barang</th>
              <th style="border: 1px solid black;">Nama Barang</th>
              <th style="border: 1px solid black;">Qty</th>
              <th style="border: 1px solid black;">Satuan</th>
              <th style="border: 1px solid black;">Keterangan</th>
              <th style="border: 1px solid black;">Harga</th>
              <th style="border: 1px solid black;">Jumlah</th>
            </tr>
          </thead>
          <tbody>
            @foreach($po_details as $key=>$po)
            <tr>
              <td>{{$key+1}}</td>
              <td>{{$po['item_code']}}</td>
              <td class="text-left">{{$po['item_name']}}</td>
              <td>{{writeIDFormat($po['qty'])}}</td>
              <td>{{$po['unit_code']}}</td>
              <td>{{$po['note']}}</td>
              <td style="text-align: right;">{{writeIDFormat($po['price'])}}</td>
              <td style="text-align: right;">{{writeIDFormat($po['amount'])}}</td>
            </tr>
            @endforeach
            @if($ppn_used==1)
            <tr>
              <td colspan="7" style="text-align: center;"> Jumlah Sebelum PPN </td>
              <td style="text-align: right;"> {{writeIDFormat($subtotal)}} </td>
            </tr>
            <tr>
              <td colspan="7" style="text-align: center;"> PPN {{ $ppn }}% </td>
              <td style="text-align: right;"> {{writeIDFormat($ppn_value)}} </td>
            </tr>
            @endif
            <tr>
              <td colspan="7" style="text-align: center;"> TOTAL </td>
              <td style="text-align: right;"> {{writeIDFormat($total)}} </td>
            </tr>
          </tbody>
        </table>

      </div>


      <div style="clear:both;">
        <table class="head table table-sm table-borderless mt-3 mb-0" style="font-size: x-small;">
          <tbody>
            <tr>
              <td class="font-weight-bold">SYARAT PEMBAYARAN:</td>
              <td style="text-align: right;"> <b>NO PENAWARAN</b> : {{$quotation_no}}</td>
            </tr>
            <tr>
              <td class="font-weight-bold" colspan="2"> PERSYARATAN :</td>
            </tr>
            @foreach($po_tcs as $key=>$tc)
            <tr>
              <td colspan="2"> {{$key+1}}. {{$tc['content']}}</td>
            </tr>
            @endforeach
          </tbody>
        </table>
      </div>

      @if($note!='')
      <div class="mt-3" style="font-size: x-small;">
        <label class="font-weight-bold" style="margin: 0px;"> CATATAN : </label>
        <pre style=" display: block; font-family: sans-serif; white-space: pre; margin: 0;">{{$note}}</pre>
      </div>
      @endif

      <table class="text-center mt-3" style="border: solid 1px #000 !important; width:100%;">
        <tr>
          <td rowspan="2" class="font-weight-bold" style="padding:0px;">ORDER PEMBELIAN DITERIMA DAN DISETUJUI</td>
          <td colspan="2" class="font-weight-bold" style="padding:0px;"> MEDAN, {{$published_at_id}} </td>
        </tr>
        <tr>
          <td colspan="2" class="font-weight-bold" style="padding:0px;"> PT ARMADA KREATIF INDOPASIFIK </td>
        </tr>
        <tr>
          <td style="width:200px; padding:0px;"></td>
          <td style="padding:0px;">Dibuat Oleh</td>
          <td style="padding:0px;">Disetujui Oleh</td>
        </tr>
        <tr>
          <td colspan="3" style="height: 50px;"></td>
        </tr>
        <tr>
          <td style="padding:0px;"></td>
          <td style="padding:0px;">{{ $signator_creator ? $signator_creator['fullname'] : '' }}</td>
          <td style="padding:0px;">{{ $signator_approver ? $signator_approver['fullname'] : '' }}</td>
        </tr>
        <tr>
          <td style="padding:0px;">SUPPLIER</td>
          <td style="padding:0px;">{{ $signator_creator ? $signator_creator['position'] : '' }}</td>
          <td style="padding:0px;">{{ $signator_approver ? $signator_approver['position'] : '' }}</td>
        </tr>
      </table>

    </div>
  </main>

</body>

</html>