<table>
    <tr>
        <td colspan="2"
            style="font-size: 16px; font-weight: bold; background-color: #4e73df; color: #ffffff; text-align: center; height: 30px; vertical-align: middle;">
            SCAN SUMMARY REPORT</td>
    </tr>
    <tr>
        <td style="font-weight: bold; width: 150px;">Jancode</td>
        <td style="width: 250px;">{{ $jancode->jancode }}</td>
    </tr>
    <tr>
        <td style="font-weight: bold;">Description</td>
        <td>{{ $jancode->description }}</td>
    </tr>
    <tr>
        <td style="font-weight: bold;">Size</td>
        <td>{{ $jancode->size }}</td>
    </tr>
    <tr>
        <td style="font-weight: bold;">Color</td>
        <td>{{ $jancode->color }}</td>
    </tr>
    <tr>
        <td style="font-weight: bold;">Country</td>
        <td>{{ $jancode->country }}</td>
    </tr>
    <tr>
        <td colspan="2"></td>
    </tr>

    @php 
        $balance = $jancode->qty - $scanned;

        $balanceColor = '#e74a3b'; // yellow (warning)
        $balanceTextColor = '#000000';

        if ($balance < 0) {
            $balanceColor = '#e74a3b'; // red (danger)
            $balanceTextColor = '#ffffff';
        } elseif ($balance == 0) {
            $balanceColor = '#1cc88a'; // green (success)
            $balanceTextColor = '#ffffff';
        }
    @endphp
    <tr>
        <td style="font-weight: bold; background-color: #eaecf4;">Qty Master</td>
        <td style="font-weight: bold; background-color: #eaecf4; text-align: right;">{{ $jancode->qty }}</td>
    </tr>
    <tr>
        <td style="font-weight: bold; background-color: #1cc88a; color: #ffffff;">Total Scanned</td>
        <td style="font-weight: bold; background-color: #1cc88a; color: #ffffff; text-align: right;">{{ $scanned }}</td>
    </tr>
    <tr>
        <td style="font-weight: bold; background-color: {{ $balanceColor }}; color: {{ $balanceTextColor }};">Balance</td>
        <td style="font-weight: bold; background-color: {{ $balanceColor }}; color: {{ $balanceTextColor }}; text-align: right;">{{ $balance }}</td>
    </tr>
</table>
