<html style="width:100%;">

<head>
  <style>
    *{
      padding:0px;
      margin:0px;
      box-sizing: border-box;
    }

    html{
      font-family:Calibri;
    }

    html,body,main{
      width:100%;
    }

    th,td{
      padding:2px;
    }

    .head td {
      padding: 0;
    }
  </style>
</head>

<body>
  <main style="padding:0px 25px 0px 29px;">
    <div style="width:100%;  border:solid 1px #000; font-size:14px;">
      <div style="width:100%; text-align:center;" class="text-center">
        #{{$id}}
        <br>
        Perincian Standby <br>
        {{$asal}} - {{$xto}} 
      </div>
      <table style="font-size: 12px; ">
        <tr>
          <td > No.Polisi </td>
          <td> : </td>
          <td> {{$no_pol}} </td>
        </tr>
        <tr>
          <td> Nama Supir </td>
          <td> : </td>
          <td> {{$supir}} </td>
        </tr>

        @if($kernet)
        <tr>
          <td> Nama Kernet </td>
          <td> : </td>
          <td> {{$kernet}} </td>
        </tr>
        @endif

        <tr>
          <td> Pertanggal </td>
          <td> : </td>
          <td> @foreach($standby_trx_details as $k=>$v) 
            @if($k>0) {{ ',' }} @endif
            {{ date('d-m-Y',strtotime($v["tanggal"])) }} 
          @endforeach </td>
        </tr>
      </table>

      <table style="font-size: 12px; width:100%;">
        <tbody>
          @foreach($standby_details as $k=>$v)
          <tr>
            <td style="text-align: left;">{{ $v["description"] }} 
              @if(count($standby_trx_details)>1) 
              (                  
                {{ number_format(count($standby_trx_details), 0,',','.') }}
                x
                Rp. {{ number_format($v["amount"], 0,',','.') }}
              )
              @endif
            </td>
            <td>:</td>
            <td>Rp. </td>
            <td style="width:50px; text-align: right;">{{ number_format((count($standby_trx_details) * $v["amount"]), 0,',','.') }}</td>
          </tr>
          @endforeach
          <tr>
            <td colspan="2" style="font-weight: bold;">
                Total
            </td>
            <td style="font-weight: bold;">Rp. </td>
            <td style="font-weight: bold;">
              {{ number_format(count($standby_trx_details) * $standby_mst['amount'], 0,',','.') }}
            </td>
          </tr>
          <tr>
            <td colspan="4" style="text-align: right;">
                Dibuat tanggal:{{ date('d-m-Y H:i:s',strtotime($created_at)) }} (#{{$standby_mst_id}})
            </td>
          </tr>
        </tbody>
      </table>

      <table style="width:100%; font-size: 12px; ">
        <tr>
          <td style="text-align: center; width:50%;"> Diserahkan Oleh :</td>
          <td style="text-align: center; width:50%;"> Diterima Oleh :</td>
        </tr>
        <tr>
          <td style="height:50px;"><td>
        </tr>
        <tr>
          <td style="text-align: center;"> ({{$user_1}}) </td>
          <td style="text-align: center;"> (____________________) </td>
        </tr>
      </table>
    </div>
  </main>

</body>

</html>