<!doctype html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="referrer" content="no-referrer">
  <title>صيانة مؤقتة — Mart.ps</title>
  <link rel="stylesheet" href="{{ asset('assets/error.css') }}">
</head>
<body class="error-page">
  <main class="card">
    <p class="code" aria-hidden="true">503</p>
    <h1>صيانة مؤقتة</h1>
    <p class="message">الخدمة غير متاحة مؤقتًا. يرجى المحاولة لاحقًا.</p>
    <a class="primary" href="{{ route('home') }}">إعادة المحاولة</a>
  </main>
</body>
</html>
