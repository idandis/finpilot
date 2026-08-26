<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <title>Voucher SPA - Nadia</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            color: #e5e7eb;
            margin: 0;
            padding: 40px;
            background: #0b1023;
        }
        .ticket {
            position: relative;
            max-width: 420px;
            margin: 0 auto;
            background: linear-gradient(135deg, #1e1b4b, #1e293b 55%, #3b0764);
            border: 1px solid rgba(232, 121, 249, 0.25);
            border-radius: 14px;
            padding: 28px;
        }
        .star {
            position: absolute;
            border-radius: 50%;
            background: #ffffff;
            opacity: 0.5;
        }
        .badge {
            display: inline-block;
            background: #10b981;
            color: #ffffff;
            font-size: 10px;
            font-weight: bold;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            padding: 4px 10px;
            border-radius: 999px;
            margin-bottom: 16px;
        }
        .label {
            font-size: 10px;
            font-weight: bold;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: #e879f9;
            margin-bottom: 6px;
        }
        h1 {
            font-size: 24px;
            margin: 0 0 2px;
            color: #ffffff;
        }
        .place {
            font-size: 12px;
            color: #9ca3af;
            margin: 0 0 16px;
        }
        .message {
            font-size: 12px;
            color: #cbd5e1;
            line-height: 1.6;
            margin: 0 0 6px;
        }
        .divider {
            border-top: 1px dashed rgba(255, 255, 255, 0.2);
            margin: 20px 0;
        }
        table.meta {
            width: 100%;
            font-size: 10px;
            color: #6b7280;
        }
        table.meta td.valid {
            text-align: right;
            color: #34d399;
            font-weight: bold;
        }
        .from {
            margin-top: 20px;
            font-size: 11px;
            color: #9ca3af;
        }
        .from strong {
            color: #e5e7eb;
        }
        .love {
            margin-top: 24px;
            text-align: center;
            font-size: 12px;
            color: #d8b4fe;
        }
    </style>
</head>
<body>
    <div class="ticket">
        <span class="star" style="top: 12px; left: 60px; width: 2px; height: 2px;"></span>
        <span class="star" style="top: 24px; left: 140px; width: 1px; height: 1px;"></span>
        <span class="star" style="top: 40px; left: 320px; width: 2px; height: 2px;"></span>
        <span class="star" style="top: 60px; left: 30px; width: 1px; height: 1px;"></span>
        <span class="star" style="top: 90px; left: 360px; width: 1px; height: 1px;"></span>
        <span class="star" style="top: 130px; left: 20px; width: 2px; height: 2px;"></span>
        <span class="star" style="top: 160px; left: 350px; width: 1px; height: 1px;"></span>
        <span class="star" style="top: 200px; left: 45px; width: 1px; height: 1px;"></span>
        <span class="star" style="top: 220px; left: 300px; width: 2px; height: 2px;"></span>
        <span class="star" style="top: 250px; left: 150px; width: 1px; height: 1px;"></span>

        <span class="badge">Senza limiti di tempo</span>

        <div class="label">Buono regalo &middot; QC Terme</div>
        <h1>Ingresso SPA</h1>
        <p class="place">San Pellegrino Terme</p>

        <p class="message">
            Missione relax: ufficialmente approvata. &#10084;
            Niente sveglie, niente orologi, niente scuse.
        </p>
        <p class="message">
            Cena inclusa, dopo le terme.
        </p>

        <div class="divider"></div>

        <table class="meta">
            <tr>
                <td>N&deg; 0000-NADIA</td>
                <td class="valid">&#10003; Valido oggi, domani, quando vuoi tu</td>
            </tr>
        </table>

        <p class="from">Da parte di <strong>Yana &amp; Lula</strong></p>
    </div>

    <p class="love">Ti vogliamo bene e non vediamo l'ora &#10084;</p>
</body>
</html>
