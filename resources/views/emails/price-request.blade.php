<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Новая заявка: Запрос цены</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #111118;
            background-color: #f9f9fb;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 16px;
            border: 1px solid rgba(17, 17, 24, 0.06);
            box-shadow: 0 10px 30px rgba(17, 17, 24, 0.04);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #1f6c9f, #154b70);
            color: white;
            padding: 35px 30px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 22px;
            font-weight: 500;
            letter-spacing: -0.02em;
        }
        .header p {
            margin: 8px 0 0;
            opacity: 0.8;
            font-size: 13px;
            font-family: monospace;
        }
        .content {
            padding: 35px 30px;
        }
        .info-block {
            background: #f9f9fb;
            border-radius: 12px;
            border: 1px solid rgba(17, 17, 24, 0.04);
            padding: 24px;
            margin-bottom: 25px;
        }
        .info-row {
            display: flex;
            margin-bottom: 14px;
            border-bottom: 1px solid rgba(17, 17, 24, 0.04);
            padding-bottom: 14px;
        }
        .info-row:last-child {
            margin-bottom: 0;
            border-bottom: none;
            padding-bottom: 0;
        }
        .info-label {
            font-weight: 600;
            color: #787774;
            width: 160px;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            flex-shrink: 0;
        }
        .info-value {
            color: #111118;
            font-size: 14px;
            word-break: break-word;
        }
        .highlight-value {
            font-weight: 600;
            color: #1f6c9f;
        }
        .message-block {
            background: #e1f3fe;
            border-left: 4px solid #1f6c9f;
            padding: 20px;
            border-radius: 0 12px 12px 0;
            margin-top: 25px;
        }
        .message-block h3 {
            margin: 0 0 8px;
            color: #154b70;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .message-block p {
            margin: 0;
            color: #111118;
            font-size: 14px;
            white-space: pre-wrap;
        }
        .footer {
            background: #f9f9fb;
            border-top: 1px solid rgba(17, 17, 24, 0.06);
            padding: 25px 30px;
            text-align: center;
            font-size: 12px;
            color: #787774;
        }
        .footer a {
            color: #1f6c9f;
            text-decoration: none;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>💰 Новая заявка: Узнать цену</h1>
            <p>[ {{ $submitted_at }} ]</p>
        </div>
        
        <div class="content">
            <div class="info-block">
                <div class="info-row">
                    <span class="info-label">Имя:</span>
                    <span class="info-value highlight-value">{{ $name }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Контакты:</span>
                    <span class="info-value">{{ $contact }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Источник:</span>
                    <span class="info-value" style="font-size: 11px; font-family: monospace;">{{ $source_url }}</span>
                </div>
            </div>

            @if($comment && $comment !== 'Не указано')
            <div class="message-block">
                <h3>💬 Комментарий к запросу:</h3>
                <p>{{ $comment }}</p>
            </div>
            @endif
        </div>

        <div class="footer">
            <p>Это автоматическое сообщение от B2B-платформы <a href="https://megapak.ru">МегаПак</a></p>
            <p>Свяжитесь с клиентом для предоставления прайс-листа и цен.</p>
        </div>
    </div>
</body>
</html>
