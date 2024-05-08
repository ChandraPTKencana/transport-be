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
      <td>{{ $v["ticket_b_a_bruto_persen"] }}</td>
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
      <td>{{ $v["ticket_b_a_tara_persen"] }}</td>
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
      <td>{{ $v["ticket_b_a_netto_persen"] }}</td>
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
  </tbody>
</table>
