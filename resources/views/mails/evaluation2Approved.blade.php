@extends('mails.layout')

@section('message-content')
    <tr>
        <td bgcolor="#ffffff" style="padding: 10px 40px 5px 40px; text-align:center;">
            <table border="0" cellpadding="0" cellspacing="0" width="100%" style="border-collapse: collapse;">
                <tr>
                <div style="margin-bottom: 10px; text-align: start;"> 
                    <h1 style="font-size: 18px; text-align: center;">CONGRATULATIONS! YOU HAVE PASSED OUR EVALUATION CHALLENGE PHASE 2 (FYT)</h1>
                    <p style="font-size: 14px;">Hello {{ $data['user'] }},</p>
                    <p style="font-size: 14px;"> 
                        You have demonstrated great skill and determination in passing the PHASE 
                        2 of our EVALUATION CHALLENGE. Congratulations!
                    </p>

                    <p style="font-size: 14px;">
                        We are confident in your abilities and hope you will continue to 
                        build on your success. Best of luck as you move forward.
                    </p>
                </div>
                </tr>
            </table>
        </td>
    </tr>
@endsection