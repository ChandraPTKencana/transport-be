<table class="line borderless text-center mt-2" style="font-size: x-small;">
      <thead class="text-center" style="background-color: #B0A4A4;">
      <tr>
          <th style="border: 1px solid black; font-weight:bold;">Amount</th>
          <th style="border: 1px solid black; font-weight:bold;">Bank Code</th>
          <th style="border: 1px solid black; font-weight:bold;">Bank Name</th>
          <th style="border: 1px solid black; font-weight:bold;">Bank Account Name</th>
          <th style="border: 1px solid black; font-weight:bold;">Bank Account Number</th>
          <th style="border: 1px solid black; font-weight:bold;">Description</th>
          <th style="border: 1px solid black; font-weight:bold;">Email</th>
          <th style="border: 1px solid black; font-weight:bold;">Method</th>
        </tr>
      </thead>
      <tbody>
        @foreach($data as $k=>$v)
        @php
          $bank_account_number=mb_strtoupper("'".$v["bank_account_number"],'UTF-8');
        @endphp
        <tr>
          <td style="border: 1px solid black;">{{ $amount }}</td>
          <td style="border: 1px solid black;">{{ $v["bank_code"] }}</td>
          <td style="border: 1px solid black;">{{ $v["bank_name"] }}</td>
          <td style="border: 1px solid black;">{{ $v["bank_account_name"] }}</td>
          <td style="border: 1px solid black;">{{ $bank_account_number }}</td>
          <td style="border: 1px solid black;">{{ $v["description"] }}</td>
          <td style="border: 1px solid black;"></td>
          <td style="border: 1px solid black;">BI FAST</td>
        </tr>
        @endforeach
      </tbody>
    </table>