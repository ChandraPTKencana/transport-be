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
    <div style="width:100%; font-size:14px;">
      <div style="width:100%; text-align:center; padding:5px; margin-top:10px;">
        <img src="{{$logo}}" width="100%">
      </div>

      <div style="width:100%; text-align:center;" class="text-center">
        #{{$ref_no0}}
      </div>
      <table style="font-size: 12px; margin-top:5px;">
        <tr>
          <td> Status </td>
          <td> : </td>
          <td style="color:#3b6c4d;"> Success </td>
        </tr>
        <tr>
          <td> Tanggal Transfer </td>
          <td> : </td>
          <td >
          {{ date('d-m-Y H:i:s',strtotime($tanggal_supir)) }}
          </td>
        </tr>
        <tr>
          <td > CustomerRef </td>
          <td> : </td>
          <td> UJ#{{$id}} </td>
        </tr>
        <tr>
          <td > No Rek </td>
          <td> : </td>
          <td> {{$supir_rek_no}} </td>
        </tr>
        <tr>
          <td > Nama </td>
          <td> : </td>
          <td> {{$supir}} </td>
        </tr>
        <tr>
          <td> Nominal </td>
          <td> : </td>
          <td> {{ number_format($nominal0, 0,',','.') }} </td>
        </tr>
      </table>
    </div>
  </main>
  @if($kernet)
  <main style="padding:0px 25px 0px 29px;">
    <div style="width:100%; font-size:14px;">
      <div style="width:100%; text-align:center; padding:5px; margin-top:10px;">
        <img src="{{$logo}}" width="100%">
      </div>

      <div style="width:100%; text-align:center;" class="text-center">
        #{{$ref_no1}}
      </div>
      <table style="font-size: 12px; margin-top:5px;">
        <tr>
          <td> Status </td>
          <td> : </td>
          <td style="color:#3b6c4d;"> Success </td>
        </tr>
        <tr>
          <td> Tanggal Transfer </td>
          <td> : </td>
          <td >
          {{ date('d-m-Y H:i:s',strtotime($tanggal_kernet)) }}
          </td>
        </tr>
        <tr>
          <td > CustomerRef </td>
          <td> : </td>
          <td> UJ#{{$id}} </td>
        </tr>
        <tr>
          <td > No Rek </td>
          <td> : </td>
          <td> {{$kernet_rek_no}} </td>
        </tr>
        <tr>
          <td > Nama </td>
          <td> : </td>
          <td> {{$kernet}} </td>
        </tr>
        <tr>
          <td> Nominal </td>
          <td> : </td>
          <td> {{ number_format($nominal1, 0,',','.') }} </td>
        </tr>
      </table>
    </div>
  </main>
  @endif
</body>

</html>