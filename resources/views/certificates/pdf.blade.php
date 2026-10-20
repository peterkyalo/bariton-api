<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Certificate - {{ $certificate->certificate_code }}</title>
    <style>
        @page {
            size: a4 landscape;
            margin: 20px;
        }
        body {
            font-family: 'Georgia', 'Times New Roman', serif;
            color: #0d1322;
            margin: 0;
            padding: 0;
            background-color: #ffffff;
            -webkit-print-color-adjust: exact;
        }
        .outer-border {
            border: 8px double #d4af37;
            padding: 24px;
            height: 92%;
            box-sizing: border-box;
            background-color: #faf9f6;
            position: relative;
        }
        .inner-border {
            border: 2px solid #0f172a;
            padding: 28px 36px;
            height: 88%;
            box-sizing: border-box;
            text-align: center;
            background-color: #ffffff;
        }
        .academy-name {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 14px;
            font-weight: 800;
            letter-spacing: 5px;
            text-transform: uppercase;
            color: #d4af37;
            margin-bottom: 6px;
        }
        .academy-subtitle {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 10px;
            letter-spacing: 2.5px;
            text-transform: uppercase;
            color: #64748b;
            margin-bottom: 22px;
        }
        .certificate-title {
            font-size: 34px;
            font-weight: normal;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: #0f172a;
            margin: 0 0 10px 0;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 12px;
            display: inline-block;
        }
        .certify-text {
            font-size: 13px;
            font-style: italic;
            color: #475569;
            margin: 14px 0 8px 0;
        }
        .holder-name {
            font-size: 36px;
            font-weight: bold;
            color: #0f172a;
            margin: 6px 0 14px 0;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #d4af37;
            display: inline-block;
            padding: 0 40px 4px 40px;
        }
        .completion-text {
            font-size: 14px;
            color: #334155;
            max-width: 650px;
            margin: 0 auto 12px auto;
            line-height: 1.6;
        }
        .course-title {
            font-size: 24px;
            font-weight: bold;
            color: #0f172a;
            margin: 4px 0 16px 0;
            letter-spacing: 0.5px;
        }
        .signatures-table {
            width: 100%;
            margin-top: 25px;
        }
        .sig-col {
            width: 33.33%;
            vertical-align: bottom;
            text-align: center;
        }
        .sig-line {
            width: 160px;
            border-top: 1px solid #94a3b8;
            margin: 0 auto 6px auto;
        }
        .sig-name {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 11px;
            font-weight: bold;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .sig-title {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 9px;
            color: #64748b;
        }
        .qr-box {
            display: inline-block;
            text-align: center;
        }
        .qr-code-img {
            width: 80px;
            height: 80px;
            margin: 0 auto;
        }
        .verify-code {
            font-family: 'Courier New', Courier, monospace;
            font-size: 10px;
            font-weight: bold;
            color: #0f172a;
            margin-top: 4px;
            letter-spacing: 1px;
        }
        .verify-hint {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 8px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .revoked-banner {
            position: absolute;
            top: 40%;
            left: 10%;
            width: 80%;
            text-align: center;
            font-size: 72px;
            font-weight: 900;
            color: rgba(220, 38, 38, 0.45);
            border: 8px solid rgba(220, 38, 38, 0.5);
            padding: 10px;
            transform: rotate(-12deg);
            text-transform: uppercase;
            letter-spacing: 8px;
            pointer-events: none;
        }
        .replaced-banner {
            position: absolute;
            top: 42%;
            left: 15%;
            width: 70%;
            text-align: center;
            font-size: 40px;
            font-weight: 900;
            color: rgba(202, 138, 4, 0.5);
            border: 6px solid rgba(202, 138, 4, 0.55);
            padding: 10px;
            transform: rotate(-10deg);
            text-transform: uppercase;
            letter-spacing: 4px;
            pointer-events: none;
        }
    </style>
</head>
<body>
    <div class="outer-border">
        @if($certificate->status === 'revoked')
            <div class="revoked-banner">REVOKED</div>
        @elseif($certificate->status === 'replaced')
            <div class="replaced-banner">REPLACED BY NEW ISSUE</div>
        @endif

        <div class="inner-border">
            <div class="academy-name">Bariton Music Academy</div>
            <div class="academy-subtitle">Conservatory of Performing Arts & Masterclasses • Nairobi, Kenya</div>

            <div class="certificate-title">
                @if($certificate->path_type === 'validation')
                    Certificate of Skills Validation
                @else
                    Certificate of Mastery
                @endif
            </div>

            <div class="certify-text">This is proudly presented and awarded to</div>

            <div class="holder-name">{{ $certificate->holder_name }}</div>

            <div class="completion-text">
                @if($certificate->path_type === 'validation')
                    who has formally demonstrated professional competency through rigorous assessment examinations for
                @else
                    for successfully fulfilling all curriculum requirements, conservatory performance rubrics, and the final examination for
                @endif
            </div>

            <div class="course-title">{{ $certificate->course_title }}</div>

            <table class="signatures-table">
                <tr>
                    <td class="sig-col">
                        <div class="sig-line"></div>
                        <div class="sig-name">Conservatory Dean</div>
                        <div class="sig-title">Academic Directorate</div>
                        <div style="font-size: 9px; color: #64748b; margin-top: 4px;">
                            Issued: {{ $certificate->issued_at ? $certificate->issued_at->format('F j, Y') : now()->format('F j, Y') }}
                        </div>
                    </td>
                    <td class="sig-col">
                        <div class="qr-box">
                            @if(!empty($qrCodeBase64))
                                <img src="data:image/svg+xml;base64,{{ $qrCodeBase64 }}" class="qr-code-img" alt="Verification QR Code"/>
                            @endif
                            <div class="verify-code">{{ $certificate->certificate_code }}</div>
                            <div class="verify-hint">Scan to Verify Authenticity</div>
                        </div>
                    </td>
                    <td class="sig-col">
                        <div class="sig-line"></div>
                        <div class="sig-name">Lead Instructor</div>
                        <div class="sig-title">Faculty of Applied Music</div>
                        <div style="font-size: 9px; color: #64748b; margin-top: 4px;">
                            Verification Code: {{ $certificate->certificate_code }}
                        </div>
                    </td>
                </tr>
            </table>
        </div>
    </div>
</body>
</html>
