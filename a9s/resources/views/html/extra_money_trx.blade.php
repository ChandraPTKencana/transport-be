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
        #{{$id}} {{$is_transition ? '(P)' : ''}}
        <br>
        Perincian Uang Tambahan <br>
        {{$asal}} - {{$extra_money['xto']}} 
      </div>
      <table style="font-size: 12px; ">
        <tr>
          <td > No.Polisi </td>
          <td> : </td>
          <td> {{$no_pol}} </td>
        </tr>
        <tr>
          <td> Nama Pekerja </td>
          <td> : </td>
          <td> {{$employee_name}} </td>
        </tr>

        <tr>
          <td> Pertanggal </td>
          <td> : </td>
          <td> {{ date('d-m-Y',strtotime($tanggal)) }}  </td>
        </tr>

        <tr>
          <td> Deskripsi </td>
          <td> : </td>
          <td> {{ $extra_money['description'] }} </td>
        </tr>

        <tr>
          <td> Total </td>
          <td> : </td>
          <td> Rp. {{ number_format($extra_money['nominal'] * $extra_money['qty'] , 0,',','.') }} </td>
        </tr>
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